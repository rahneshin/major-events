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
| آخرین مرحله‌ی DONE | 04 |
| مرحله‌ی در حال انجام | — (آماده Step 05) |
| تاریخ آخرین به‌روزرسانی | ۱۴۰۵/۰۸/۲۹ |

---

## ۲. جدول وضعیت مراحل

وضعیت‌ها: ⬜ شروع‌نشده · 🟨 در حال انجام · ✅ انجام‌شده · 🟥 مسدود

| # | مرحله | وضعیت | شاخه | تست‌ها | توضیح |
|---|---|---|---|---|---|
| 00 | نصب Laravel، Git، محیط | ✅ | step/00-tooling-and-locale | سبز | اسکلت + Pest + Larastan + Livewire + locale fa + CI |
| 01 | زیرساخت UI/RTL/جلالی | ✅ | step/01-ui-rtl-jalali | سبز | لایوت RTL، کامپوننت‌ها، Jalali، PhoneNumber، styleguide |
| 02 | ورود با موبایل (OTP) | ✅ | step/02-otp-login | سبز | OTP، Livewire، rate-limit |
| 03 | نوع حساب و Entitlement | ✅ | step/03-account-type-entitlement | سبز + تست دستی | onboarding، ارتقا، plans.php |
| 04 | سازمان مجازی و اعضا | ✅ | step/04-organizations | نوشته‌شده | سازمان، عضو، دعوت، نقش، انتقال مالکیت |
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

منبع: `config/plans.php`

| کلید | کاربر عادی | کاربر سازمانی |
|---|---|---|
| active_events | 1 | نامحدود |
| committees_per_event | 3 | نامحدود |
| members_per_event | 10 | نامحدود |
| tasks_per_event | 50 | نامحدود |
| organizations | 0 | 3 |
| knowledge_ai_requests_per_month | 0 | 100 |

---

## ۴. Decision Log (تصمیم‌های معماری)

| تاریخ | تصمیم | دلیل | جایگزین‌های ردشده | مرحله |
|---|---|---|---|---|
| ۱۴۰۵/۰۸/۲۹ | SQLite تا Step 16 | سادگی محلی | PostgreSQL از ابتدا | 00 |
| ۱۴۰۵/۰۸/۲۹ | ورود فقط OTP | سادگی کاربر ایرانی | ایمیل/رمز | 02 |
| ۱۴۰۵/۰۸/۲۹ | SMS driver=log | تا انتخاب سرویس | — | 02 |
| ۱۴۰۵/۰۸/۲۹ | کاهش سازمانی→عادی ممنوع | جلوگیری از نقض سقف | کاهش مشروط | 03 |
| ۱۴۰۵/۰۸/۲۹ | محدودیت‌ها فقط EntitlementService | تغییر سقف بدون کد | if پراکنده | 03 |
| ۱۴۰۵/۰۸/۲۹ | مالک نمی‌تواند ترک کند مگر انتقال | یکپارچگی مالکیت | حذف آزاد مالک | 04 |

### تصمیم‌های باز
- [ ] سرویس پیامک ایرانی
- [ ] RAG داخل Laravel یا FastAPI (Step 15)
- [ ] کتابخانه PDF فارسی (Step 14)

---

## ۵. Manifest فایل‌های مخزن

### Account / Entitlement (03)
AccountType, EntitlementService, plans.php, SelectAccountType, AccountPage

### Organizations (04)
app/Enums/OrgRole.php, OrganizationType.php
app/Models/Organization.php, OrganizationInvitation.php
app/Services/OrganizationService.php
app/Policies/OrganizationPolicy.php
app/Livewire/Orgs/OrganizationIndex|Form|Show
database/migrations/2026_11_19_000003_create_organizations_tables.php
lang/fa/org.php
tests/Feature/Orgs/OrganizationTest.php
users.current_organization_id

### Routeها (جدید)
/organizations, /organizations/create, /organizations/{id}, /organizations/{id}/edit

---

## ۶. مشکلات شناخته‌شده

| # | شرح | شدت | وضعیت |
|---|---|---|---|
| 1 | فونت Vazirmatn دستی | پایین | باز |
| 2 | SMS واقعی انتخاب نشده | متوسط | باز |
| 3 | پذیرش دعوت از لینک عمومی هنوز UI جدا ندارد (accept از سرویس) | پایین | باز |

---

## ۷. Changelog

## UPDATE — Step 04 — سازمان مجازی و اعضا — ۱۴۰۵/۰۸/۲۹
Status: DONE (کد کامل؛ تست‌ها نوشته‌شده — اجرا محلی)
Branch: step/04-organizations
Commit: feat(orgs): organizations, members, invitations, roles
Files created: [Organization*, OrgRole, OrganizationType, OrganizationService, OrganizationPolicy, Livewire Orgs/*, migration, lang/fa/org.php, OrganizationTest]
Files modified: [User.php, routes/web.php, AppServiceProvider.php]
Migrations: [organizations, organization_user, organization_invitations, users.current_organization_id]
Routes added: [/organizations*]
Decisions: [مالک فقط با transfer ترک می‌کند؛ سقف organizations از Entitlement]
Tests: OrganizationTest
Next suggested step: 05 — رویداد (هسته)

## UPDATE — Step 03 — نوع حساب و Entitlement — ۱۴۰۵/۰۸/۲۹
Status: DONE (تست دستی مرورگر تأیید شد)
Branch: step/03-account-type-entitlement
...

---

## ۸. روال به‌روزرسانی بعد از هر مرحله

1. فایل‌های چت‌بات را جایگزین کن
2. دستورها را اجرا کن
3. تست دستی انجام بده
4. بلوک UPDATE را بچسبان
5. Commit و push کن
