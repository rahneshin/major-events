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
| آخرین مرحله‌ی DONE | 03 |
| مرحله‌ی در حال انجام | — (آماده Step 04) |
| تاریخ آخرین به‌روزرسانی | ۱۴۰۵/۰۸/۲۹ |

---

## ۲. جدول وضعیت مراحل

وضعیت‌ها: ⬜ شروع‌نشده · 🟨 در حال انجام · ✅ انجام‌شده · 🟥 مسدود

| # | مرحله | وضعیت | شاخه | تست‌ها | توضیح |
|---|---|---|---|---|---|
| 00 | نصب Laravel، Git، محیط | ✅ | step/00-tooling-and-locale | سبز | اسکلت + Pest + Larastan + Livewire + locale fa + CI |
| 01 | زیرساخت UI/RTL/جلالی | ✅ | step/01-ui-rtl-jalali | سبز | لایوت RTL، کامپوننت‌ها، Jalali، PhoneNumber، styleguide |
| 02 | ورود با موبایل (OTP) | ✅ | step/02-otp-login | سبز | OTP، Livewire، rate-limit، تست‌ها پاس |
| 03 | نوع حساب و Entitlement | ✅ | step/03-account-type-entitlement | نوشته‌شده | AccountType، plans.php، EntitlementService، onboarding، ارتقا |
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
| 17 | Prompt Composer و AI Planner | ⬜ | | | |
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
| knowledge_ai_requests_per_month | 0 | 100 |
| export | PDF ساده | PDF/Word/Excel |
| مسابقات، اسپانسرینگ | ندارد | دارد |
| پایگاه دانش و AI | ندارد | دارد (با سهمیه‌ی ماهانه) |

منبع حقیقت اعداد: `config/plans.php`

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
| ۱۴۰۵/۰۸/۲۹ | کاهش سازمانی → عادی فعلاً ممنوع | سادگی؛ جلوگیری از نقض سقف سازمان/رویداد | کاهش مشروط | 03 |
| ۱۴۰۵/۰۸/۲۹ | محدودیت‌ها فقط از EntitlementService | تغییر سقف بدون تغییر کد | if پراکنده روی account_type | 03 |
| — | مبلغ‌ها به ریال، عدد صحیح | جلوگیری از خطای اعشاری | float/decimal | 09 |
| — | قرعه و جدول مسابقات با الگوریتم قطعی | قابل ممیزی و تست | استفاده از AI | 12 |
| — | AI فقط پیشنهاد می‌دهد؛ اجرا پس از تأیید انسان | کنترل کیفیت و مسئولیت | اجرای خودکار | 18 |

### تصمیم‌های باز
- [ ] سرویس پیامک ایرانی (کاوه‌نگار / ملی‌پیامک / sms.ir) — فعلاً LogSmsGateway
- [ ] RAG داخل Laravel یا سرویس FastAPI جدا؟ (Step 15)
- [ ] سیاست آپلود و مجوز منابع (Step 15)
- [ ] مدل embedding و مدل زبانی (Step 16/17)
- [ ] کتابخانه‌ی PDF فارسی (Step 14)

---

## ۵. Manifest فایل‌های مخزن

### پیکربندی و ریشه
composer.json, .env.example, config/app.php, config/sms.php, config/plans.php
phpstan.neon, pint.json, .github/workflows/ci.yml, vite.config.js
tests/Pest.php

### Auth / OTP (Step 02)
app/Models/User.php, OtpCode.php
app/Services/OtpService.php, Sms/*
app/Livewire/Auth/PhoneLogin, VerifyOtp, CompleteProfile, SelectAccountType

### Account / Entitlement (Step 03)
app/Enums/AccountType.php
app/Services/EntitlementService.php
app/Exceptions/LimitExceededException.php
app/Http/Middleware/EnsureAccountTypeSelected.php
app/Models/AccountUpgrade.php
app/Livewire/Account/AccountPage.php
config/plans.php
lang/fa/account.php
tests/Feature/Account/EntitlementTest.php

### Support
app/Support/PhoneNumber.php, Jalali.php, helpers.php (feature, limit_reached)

### Migrationها
- users (phone, uuid, account_type, softDeletes)
- otp_codes
- account_upgrades

### Routeها
- /login, /verify, /complete-profile, /select-account-type, POST /logout
- /account (نیازمند account.type)
- /styleguide (local)

---

## ۶. مشکلات شناخته‌شده و بدهی فنی

| # | شرح | شدت | مرحله‌ی کشف | وضعیت |
|---|---|---|---|---|
| 1 | فایل‌های فونت Vazirmatn باید دستی در resources/fonts/vazirmatn قرار گیرند | پایین | 01 | باز |
| 2 | اخطار deprecation در Verta::instance (پکیج بالادستی) | پایین | 01 | باز |
| 3 | درایور واقعی پیامک هنوز انتخاب نشده (فعلاً log) | متوسط | 02 | باز |

---

## ۷. Changelog

## UPDATE — Step 03 — نوع حساب و Entitlement — ۱۴۰۵/۰۸/۲۹
Status: DONE (کد کامل؛ تست‌ها نوشته شده — اجرا روی ماشین محلی)
Branch: step/03-account-type-entitlement
Commit: feat(accounts): account types, entitlement service, onboarding and upgrade
Files created: [
  app/Enums/AccountType.php,
  config/plans.php,
  app/Exceptions/LimitExceededException.php,
  app/Services/EntitlementService.php,
  app/Http/Middleware/EnsureAccountTypeSelected.php,
  database/migrations/2026_11_19_000002_create_account_upgrades_table.php,
  app/Models/AccountUpgrade.php,
  app/Livewire/Auth/SelectAccountType.php,
  app/Livewire/Account/AccountPage.php,
  resources/views/livewire/auth/select-account-type.blade.php,
  resources/views/livewire/account/account-page.blade.php,
  lang/fa/account.php,
  tests/Feature/Account/EntitlementTest.php
]
Files modified: [
  app/Models/User.php,
  app/Livewire/Auth/CompleteProfile.php,
  app/Support/helpers.php,
  app/Providers/AppServiceProvider.php,
  bootstrap/app.php,
  routes/web.php,
  database/factories/UserFactory.php
]
Migrations: [account_upgrades]
New env keys: []
New packages: []
Routes added: [/select-account-type, /account]
Decisions: [کاهش سازمانی→عادی ممنوع؛ همه محدودیت‌ها از EntitlementService؛ ارتقا بدون پرداخت]
Known issues / TODO: []
Tests: EntitlementTest نوشته شده
Next suggested step: 04 — سازمان مجازی و اعضا

## UPDATE — Step 02 — ورود با موبایل OTP — ۱۴۰۵/۰۸/۲۹
Status: DONE
Branch: step/02-otp-login
...

---

## ۸. روال به‌روزرسانی بعد از هر مرحله

1. فایل‌های چت‌بات را جایگزین کن
2. دستورها را اجرا کن
3. تست دستی انجام بده
4. بلوک UPDATE را در بخش ۷ بچسبان و جداول را به‌روز کن
5. Commit و push کن
