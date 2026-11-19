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
| آخرین مرحله‌ی DONE | 00 |
| مرحله‌ی در حال انجام | — |
| تاریخ آخرین به‌روزرسانی | ۱۴۰۵/۰۸/۲۹ |

---

## ۲. جدول وضعیت مراحل

وضعیت‌ها: ⬜ شروع‌نشده · 🟨 در حال انجام · ✅ انجام‌شده · 🟥 مسدود

| # | مرحله | وضعیت | شاخه | تست‌ها | توضیح |
|---|---|---|---|---|---|
| 00 | نصب Laravel، Git، محیط | ✅ | step/00-tooling-and-locale | سبز | اسکلت + Pest + Larastan + Livewire + locale fa + CI |
| 01 | زیرساخت UI/RTL/جلالی | ⬜ | | | |
| 02 | ورود با موبایل (OTP) | ⬜ | | | |
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
| export | PDF ساده | PDF/Word/Excel |
| مسابقات، اسپانسرینگ | ندارد | دارد |
| پایگاه دانش و AI | ندارد | دارد (با سهمیه‌ی ماهانه) |

---

## ۴. Decision Log (تصمیم‌های معماری)

| تاریخ | تصمیم | دلیل | جایگزین‌های ردشده | مرحله |
|---|---|---|---|---|
| ۱۴۰۵/۰۸/۲۹ | استفاده از SQLite تا Step 16 | سادگی توسعه محلی با Herd | PostgreSQL از ابتدا | 00 |
| ۱۴۰۵/۰۸/۲۹ | سطح Larastan = 5 | تعادل بین سخت‌گیری و سرعت اولیه | سطح بالاتر | 00 |
| — | ورود فقط با OTP موبایل، بدون رمز | سادگی برای کاربر ایرانی | ایمیل/رمز | 02 |
| — | محدودیت‌ها در `config/plans.php` و `EntitlementService` | تغییر سقف بدون تغییر کد | `if` پراکنده | 03 |
| — | مبلغ‌ها به ریال، عدد صحیح | جلوگیری از خطای اعشاری | float/decimal | 09 |
| — | قرعه و جدول مسابقات با الگوریتم قطعی | قابل ممیزی و تست | استفاده از AI | 12 |
| — | AI فقط پیشنهاد می‌دهد؛ اجرا پس از تأیید انسان | کنترل کیفیت و مسئولیت | اجرای خودکار | 18 |

### تصمیم‌های باز
- [ ] سرویس پیامک ایرانی (Step 02)
- [ ] کاهش حساب سازمانی به عادی مجاز است؟ (Step 03)
- [ ] RAG داخل Laravel یا سرویس FastAPI جدا؟ (Step 15)
- [ ] سیاست آپلود و مجوز منابع (Step 15)
- [ ] مدل embedding و مدل زبانی (Step 16/17)
- [ ] کتابخانه‌ی PDF فارسی (Step 14)

---

## ۵. Manifest فایل‌های مخزن
composer.json                          [Step 00] livewire + pest + larastan

.env.example                           [Step 00] APP_LOCALE=fa

config/app.php                         [Step 00] Asia/Tehran + fa

phpstan.neon                           [Step 00]

pint.json                              [Step 00]

.github/workflows/ci.yml               [Step 00]

README.md                              [Step 00]

docs/ai/00-MASTER-CONTEXT.md           [Step 00]

docs/ai/10-STEPS-PART1.md              [Step 00]

docs/ai/11-STEPS-PART2.md              [Step 00]

docs/ai/90-PROGRESS.md                 [Step 00]

docs/spec/product-roadmap.md           [Step 00]Text
### Migrationهای موجود
- 0001_01_01_000000_create_users_table
- 0001_01_01_000001_create_cache_table
- 0001_01_01_000002_create_jobs_table

### Routeهای اصلی
- `/` ← welcome

### Env keyها
APP_NAME, APP_ENV, APP_KEY, APP_DEBUG, APP_URL, APP_LOCALE, APP_FALLBACK_LOCALE, APP_FAKER_LOCALE, DB_CONNECTION

### پکیج‌ها
- laravel/framework ^13.17
- livewire/livewire
- pestphp/pest + pestphp/pest-plugin-laravel
- larastan/larastan
- laravel/pint
- tailwindcss ^4

---

## ۶. مشکلات شناخته‌شده و بدهی فنی

| # | شرح | شدت | مرحله‌ی کشف | وضعیت |
|---|---|---|---|---|
| — | — | — | — | — |

---

## ۷. Changelog

## UPDATE — Step 00 — نصب ابزارها و تنظیمات محیط — ۱۴۰۵/۰۸/۲۹
Status: DONE
Branch: step/00-tooling-and-locale
Commit: chore: complete Step 00 — Pest, Larastan, Livewire, locale fa, CI, README and progress
Files created: [phpstan.neon, pint.json, .github/workflows/ci.yml]
Files modified: [.env.example, config/app.php, README.md, docs/ai/90-PROGRESS.md]
Migrations: []
New env keys: []
New packages: [livewire/livewire, pestphp/pest, pestphp/pest-plugin-laravel, larastan/larastan]
Routes added: []
Decisions: [SQLite تا Step 16، Larastan level 5]
Known issues / TODO: []
Tests: passed
Next suggested step: 01

---

## ۸. روال به‌روزرسانی بعد از هر مرحله

1. فایل‌های چت‌بات را جایگزین کن
2. دستورها را اجرا کن
3. تست دستی انجام بده
4. بلوک UPDATE را در بخش ۷ بچسبان و جداول را به‌روز کن
5. Commit و push کن