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
| شاخه‌ی کاری فعلی | `step/06-event-members` |
| شاخه‌ی اصلی | main (هنوز فقط Step 00 — مرج نشده) |
| آخرین مرحله‌ی DONE | **06** |
| مرحله‌ی بعدی پیشنهادی | 07 — کمیته‌ها |
| تاریخ آخرین به‌روزرسانی | ۱۴۰۵/۰۸/۲۹ |

---

## ۲. جدول وضعیت مراحل

وضعیت‌ها: ⬜ شروع‌نشده · 🟨 در حال انجام · ✅ انجام‌شده · 🟥 مسدود

| # | مرحله | وضعیت | شاخه | تست‌ها | توضیح |
|---|---|---|---|---|---|
| 00 | نصب Laravel، Git، محیط | ✅ | step/00-tooling-and-locale | سبز | اسکلت + Pest + Larastan + Livewire + locale fa + CI |
| 01 | زیرساخت UI/RTL/جلالی | ✅ | step/01-ui-rtl-jalali | سبز | لایوت RTL، کامپوننت‌ها، Jalali، PhoneNumber |
| 02 | ورود با موبایل (OTP) | ✅ | step/02-otp-login | سبز | OTP، Livewire، rate-limit، driver log |
| 03 | نوع حساب و Entitlement | ✅ | step/03-account-type-entitlement | سبز + دستی | onboarding، plans.php، ارتقا |
| 04 | سازمان مجازی و اعضا | ✅ | step/04-organizations | نوشته‌شده | سازمان، دعوت، نقش، انتقال مالکیت |
| UI | فرانت + پنل کاربر + شل ادمین | ✅ | feat/modern-panels-frontend | دستی | landing، /app، /admin، motion |
| 05 | رویداد (هسته) | ✅ | step/05-events-core | دستی | CRUD، اهداف، وضعیت، هاب |
| 06 | اعضا و نقش‌های رویداد | ✅ | step/06-event-members | دستی | event_user، دعوت، activity_logs |
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
| 20 | پنل ادمین و امنیت (Filament) | ⬜ | | شل ساده در /admin موجود است |
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
| ۱۴۰۵/۰۸/۲۹ | مالک سازمان فقط با transfer ترک می‌کند | یکپارچگی مالکیت | حذف آزاد مالک | 04 |
| ۱۴۰۵/۰۸/۲۹ | پنل ادمین موقت Livewire؛ Filament در Step 20 | سرعت UI اولیه | Filament از ابتدا | UI |
| ۱۴۰۵/۰۸/۲۹ | سازنده رویداد = event_owner خودکار | سادگی | نقش جدا | 05/06 |
| ۱۴۰۵/۰۸/۲۹ | گذار وضعیت با لیست مجاز در Enum | جلوگیری از وضعیت نامعتبر | free-form | 05 |
| ۱۴۰۵/۰۸/۲۹ | دعوت عضو با موبایل؛ اگر کاربر هست مستقیم عضو | UX | فقط لینک | 06 |

### تصمیم‌های باز
- [ ] سرویس پیامک ایرانی
- [ ] RAG داخل Laravel یا FastAPI (Step 15)
- [ ] کتابخانه PDF فارسی (Step 14)
- [ ] مرج شاخه‌های step/* به main

---

## ۵. Manifest فایل‌های مهم مخزن

### Auth / Account (02–03)
- OtpService, SmsGateway, PhoneLogin, VerifyOtp, CompleteProfile
- AccountType, EntitlementService, plans.php, SelectAccountType, AccountPage
- Middleware EnsureAccountTypeSelected

### Organizations (04)
- Organization, OrganizationInvitation, OrgRole, OrganizationType
- OrganizationService, OrganizationPolicy
- Livewire Orgs: Index, Form, Show

### UI shells
- layouts: public, app, admin, guest
- public/home.blade.php (landing)
- Livewire App\Dashboard, Admin\Dashboard
- resources/css/app.css (motion: glass, float, rise, btn-primary)

### Events (05–06)
- Enums: EventStatus, EventType, EventScale, MainGoalType, GoalKind, EventRole
- Models: Event, EventGoal, EventStakeholder, EventInvitation, ActivityLog
- Services: EventService, EventMemberService
- Policy: EventPolicy
- Livewire: EventIndex, EventForm (wizard), EventShow (hub + members tab)
- Trait: LogsActivity
- Migrations: 2026_11_20_000004_create_events_tables, 2026_11_20_000005_create_event_members_tables
- lang/fa/event.php

### Routes
- `/` home
- `/login`, `/verify`, `/complete-profile`, `/select-account-type`
- `/app` user dashboard
- `/account`
- `/organizations*`
- `/events`, `/events/create`, `/events/{uuid}`, `/events/{uuid}/edit`
- `/admin` system admin shell

---

## ۶. مشکلات شناخته‌شده

| # | شرح | شدت | وضعیت |
|---|---|---|---|
| 1 | فونت Vazirmatn ممکن است فایل woff2 کامل نباشد | پایین | باز |
| 2 | SMS واقعی انتخاب نشده (driver=log) | متوسط | باز |
| 3 | پذیرش دعوت رویداد از لینک عمومی UI جدا ندارد | پایین | باز |
| 4 | main هنوز Step 00 است؛ کار روی step/06 است | متوسط | باز — مرج در انتظار |
| 5 | تست‌های Pest برای Events/Members هنوز کامل نوشته نشده | متوسط | باز |
| 6 | بعد از pull حتماً `npm run build` و `php artisan migrate` | — | یادآوری |

---

## ۷. Changelog

## UPDATE — Step 06 — اعضا و نقش‌های رویداد — ۱۴۰۵/۰۸/۲۹
Status: DONE (کد + UI تب اعضا؛ تست دستی)
Branch: step/06-event-members
Commit: feat(events): event members, roles, invitations, activity log
Files created: [EventRole, event_user, event_invitations, activity_logs, EventInvitation, ActivityLog, EventMemberService, LogsActivity]
Files modified: [Event.php, EventPolicy, EventShow, event-show.blade.php, lang/fa/event.php]
Migrations: [2026_11_20_000005_create_event_members_tables]
Routes added: [] (همان /events/{uuid} با تب members)
Decisions: [سازنده = event_owner؛ دعوت با موبایل؛ activity log پایه]
Known issues: [UI پذیرش دعوت از لینک عمومی؛ تست Pest کامل نیست]
Next suggested step: 07 — کمیته‌ها

## UPDATE — Step 05 — رویداد (هسته) — ۱۴۰۵/۰۸/۲۹
Status: DONE
Branch: step/05-events-core
Commit: feat(events): event core with goals and status workflow
Files created: [Event*, EventGoal, EventStakeholder, EventService, EventPolicy, EventIndex/Form/Show, enums, migration, lang/fa/event.php]
Files modified: [routes/web.php, AppServiceProvider, layouts/app sidebar]
Migrations: [2026_11_20_000004_create_events_tables]
Routes added: [/events*]
Decisions: [گذار وضعیت در Enum؛ uuid در URL؛ سقف active_events]
Next suggested step: 06

## UPDATE — UI shells — فرانت + پنل کاربر + ادمین — ۱۴۰۵/۰۸/۲۹
Status: DONE
Branch: feat/modern-panels-frontend (سپس در step/05 و 06 ادامه یافت)
Files created: [layouts/public, layouts/admin, public/home, App/Dashboard, Admin/Dashboard, motion CSS]
Routes: [/app, /admin, / home landing]

## UPDATE — Step 04 — سازمان مجازی و اعضا — ۱۴۰۵/۰۸/۲۹
Status: DONE
Branch: step/04-organizations
...

## UPDATE — Step 03 — نوع حساب و Entitlement — ۱۴۰۵/۰۸/۲۹
Status: DONE
Branch: step/03-account-type-entitlement
...

---

## ۸. روال به‌روزرسانی بعد از هر مرحله

1. فایل‌های چت‌بات را جایگزین کن
2. دستورها را اجرا کن (`migrate`, `npm run build`, تست)
3. تست دستی انجام بده
4. بلوک UPDATE را بچسبان و جداول را به‌روز کن
5. Commit و push کن
