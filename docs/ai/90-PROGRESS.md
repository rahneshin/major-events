# 90 — PROGRESS (وضعیت پروژه، Manifest فایل‌ها، Decision Log، Changelog)

> **این فایل منبع حقیقت وضعیت پروژه است.** بعد از هر مرحله، بلوک `UPDATE` را در «Changelog» بچسبان، جدول وضعیت، Manifest و جدول Routeها را به‌روز کن، سپس commit کن.
> **به چت‌بات:** وضعیت واقعی مخزن را فقط از همین فایل و کد واقعی بخوان. اگر چیزی اینجا نیست، فرض نکن وجود دارد. **کد واقعی بر هر سندی برای «چه چیزی الان وجود دارد» برتری دارد.**

---

## ۱. خلاصه‌ی فنی فعلی

| مورد | مقدار |
|---|---|
| نسخه‌ی PHP (در `composer.json`/CI) | `^8.3` / 8.3 — ⚠️ **ولی `composer.lock` بسته‌هایی با نیاز PHP ≥ 8.4 دارد** (مشکل ۷؛ در 06B تعیین تکلیف شود) |
| Laravel | constraint `^13.17`؛ **نسخه‌ی قفل‌شده v13.43.0** |
| Livewire | v4.4.8 (قفل‌شده) |
| Pest / Larastan / Pint | 5.3.7 / 3.15.0 / 1.32.3 (قفل‌شده) |
| دیتابیس | SQLite (تا Step 16) |
| مخزن Git | https://github.com/rahneshin/major-events |
| شاخه‌ی کاری فعلی | `step/06b-hardening` (از سر `step/08-dashboard-site-template`؛ شامل 01 تا 07 + UI-2 + 06B) |
| شاخه‌ی اصلی | `main` — هنوز فقط Step 00؛ **مرج نشده** (مشکل ۴) |
| آخرین مرحله‌ی DONE | **06B** (کد و تست نوشته شد؛ **اجرای تست‌ها و مرج توسط امیر**)، 07، UI-2 |
| مرحله‌ی بعدی | **08a** (وظایف: دیتابیس و منطق) — شاخه‌ی `step/08-tasks` |
| تاریخ آخرین به‌روزرسانی | ۱۴۰۵/۰۸/۳۰ (06B) |

### توپولوژی شاخه‌ها (بررسی‌شده با `git merge-base --is-ancestor`)

زنجیره‌ی خطی: `main`(Step 00) ← `step/01-ui-rtl-jalali` ← `step/02-otp-login` ← `step/03-account-type-entitlement` ← `step/04-organizations` ← `feat/modern-panels-frontend` ← `step/05-events-core` ← `step/06-event-members` ← `step/07-committees` ← **`step/08-dashboard-site-template`** (= UI-2 + docs).

| شاخه | در زنجیره‌ی اصلی؟ | توضیح |
|---|---|---|
| `step/01` … `step/04-organizations` | ✅ | هسته‌ی ورود/حساب/سازمان |
| `feat/modern-panels-frontend` | ✅ | فرانت اولیه، `/app`، شل `/admin` |
| `step/05-events-core`، `step/06-event-members`، `step/07-committees` | ✅ | رویداد، اعضا، کمیته |
| `step/08-dashboard-site-template` | ✅ (سر زنجیره) | **UI-2: بازطراحی قالب؛ نام شاخه با «Step 08 وظایف» تداخل دارد — فقط نام است** |
| `step/04-system-dashboard` | ❌ **جدا** | مسیر `/system` + `System\SystemDashboard`؛ در زنجیره نیست و Gate ندارد → تکلیف در 06B |

> قاعده: شاخه‌ی Step وظایف را `step/08-tasks` (یا `step/08a-tasks-core`) از سر زنجیره بساز تا با شاخه‌ی قالب اشتباه نشود.

---

## ۲. جدول وضعیت مراحل

وضعیت‌ها: ⬜ شروع‌نشده · 🟨 در حال انجام · ✅ انجام‌شده · 🟥 مسدود

| # | مرحله | وضعیت | شاخه | تست‌ها | توضیح |
|---|---|---|---|---|---|
| 00 | نصب Laravel، Git، محیط | ✅ | step/00-tooling-and-locale | سبز | Pest + Larastan + Livewire + locale fa + CI |
| 01 | زیرساخت UI/RTL/جلالی | ✅ | step/01-ui-rtl-jalali | سبز | لایوت RTL، کامپوننت‌ها، Jalali، PhoneNumber |
| 02 | ورود با موبایل (OTP) | ✅ | step/02-otp-login | سبز | OTP، Livewire، rate-limit، driver log |
| 03 | نوع حساب و Entitlement | ✅ | step/03-account-type-entitlement | سبز + دستی | onboarding، plans.php، ارتقا |
| 04 | سازمان مجازی و اعضا | ✅ | step/04-organizations | Pest | سازمان، دعوت، نقش، انتقال مالکیت |
| UI | فرانت + پنل کاربر + شل ادمین | ✅ | feat/modern-panels-frontend | دستی | landing، /app، /admin، motion |
| 05 | رویداد (هسته) | ✅ | step/05-events-core | دستی | CRUD، اهداف، وضعیت، هاب |
| 06 | اعضا و نقش‌های رویداد | ✅ | step/06-event-members | دستی | event_user، دعوت، activity_logs |
| 07 | کمیته‌ها | ✅ | step/07-committees | Pest نوشته شده | طبق اعلام امیر انجام‌شده؛ **نتیجه‌ی `php artisan test` در PROGRESS ثبت نشده — امیر ثبت کند** |
| UI-2 | بازطراحی قالب «Lane lines» | ✅ | step/08-dashboard-site-template | دستی | توکن‌های pool/ink/flag/signal، لندینگ، داشبورد، لیست رویدادها |
| **06B** | **تثبیت و امنیت پایه** | ✅ (پچ آماده؛ تست‌ها را امیر اجرا کند) | step/06b-hardening | Pest نوشته شد | gate ادمین، `/system`، توکن دعوت هش، rate-limit، تست امنیت/نشت، Larastan در CI، pin PHP، seeder |
| 08a | وظایف: دیتابیس و منطق | ⬜ **بعدی** | (پیشنهاد) step/08-tasks | | |
| 08b | وظایف: تب رابط | ⬜ | | | |
| 08c | Kanban، تقویم، وظایف من، یادآور | ⬜ | | | |
| 09 | بودجه و درآمد | ⬜ | | | |
| 10 | ریسک | ⬜ | | | |
| 11 | اسپانسرینگ و بازاریابی | ⬜ | | | |
| 12 | مسابقات (12A–12F) | ⬜ | | | `competition_id` روی tasks در 12F اضافه می‌شود |
| 13 | داشبورد و اعلان | ⬜ | | | |
| 14 | خروجی گرفتن | ⬜ | | | |
| 15 | پایگاه دانش: آپلود/استخراج | ⬜ | | | |
| 16 | Chunk/Embedding/جست‌وجو | ⬜ | | | |
| 17 | Prompt Composer و AI Planner | ⬜ | | | |
| 18 | پیشنهاد AI ← تأیید ← وظیفه | ⬜ | | | |
| 19 | ارزیابی پسارویداد | ⬜ | | | |
| 20 | پنل ادمین و امنیت (Filament) | ⬜ | | | شل ساده در /admin موجود است (بدون gate) |
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
| features (عادی) | `tasks_basic`, `budget_basic`, `export_pdf` | `*` |

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
| ۱۴۰۵/۰۸/۳۰ | رئیس کمیته در سطح رویداد **مشتق‌شده** (`Event::roleOf`) | بدون همگام‌سازی pivot | نوشتن role در event_user | 07 |
| ۱۴۰۵/۰۸/۳۰ | حذف کمیته‌ی دارای وظیفه ممنوع | وظیفه‌ی بدون کمیته نشود | انتقال خودکار | 07 |
| ۱۴۰۵/۰۸/۳۰ | یکتایی نام کمیته در سرویس (نه DB) | softDeletes | unique در DB | 07 |
| ۱۴۰۵/۰۸/۳۰ | کلیدهای `CommitteeType` در **کد** مرجع‌اند | کد واقعی برتر است | نام‌های بلند در docs قدیمی | 07 |
| ۱۴۰۵/۰۸/۳۰ | بازطراحی قالب = مرحله‌ی **UI-2** (نه Step 08) | شماره‌ی 08 برای «وظایف» می‌ماند | تغییر شماره‌ی همه‌ی مراحل | UI-2 |
| ۱۴۰۵/۰۸/۳۰ | alias های `indigo/violet/fuchsia` → پالت `pool` در CSS | صفحات قدیمی نشکنند | بازنویسی همه‌ی صفحات | UI-2 |
| ۱۴۰۵/۰۸/۳۰ | ترتیب اجرا: 07 ← **06B** ← 08 | امنیت قبل از ماژول‌های جدید (دستور امیر) | 08 مستقیم | 06B |
| ۱۴۰۵/۰۸/۳۰ | ادمین سیستم = `users.is_super_admin` + Gate `access-admin` (middleware `can:`)؛ فقط با `php artisan user:make-admin`؛ در `$fillable` نیست؛ کاربر suspended رد می‌شود | حداقل سطح حمله | نقش از UI | 06B |
| ۱۴۰۵/۰۸/۳۰ | `/system` با cherry-pick فایل‌های `step/04-system-dashboard` + همان Gate (گزینه‌ی الف) | پیش‌فرض سند | ادغام در /admin | 06B |
| ۱۴۰۵/۰۸/۳۰ | توکن دعوت رویداد = SHA-256 در `token_hash` (قابل جست‌وجو با `findByToken` + `hash_equals`)؛ توکن خام فقط در `$invitation->plainToken` همان درخواست | یکسان‌سازی با دعوت سازمان و امکان لینک در Step 13 | bcrypt (غیرقابل جست‌وجو) | 06B |
| ۱۴۰۵/۰۸/۳۰ | rate-limit دعوت: ۲۰ دعوت در ساعت برای هر کاربر (رویداد و سازمان) | جلوگیری از اسپم و شمارش شماره‌ها | بدون محدودیت | 06B |
| ۱۴۰۵/۰۸/۳۰ | قفل نسخه‌ی PHP با `config.platform.php = 8.3.0` در composer.json (CI روی 8.3 می‌ماند) | حل ناسازگاری lock با 8.3 | ارتقای CI به 8.4 | 06B |
| ۱۴۰۵/۰۸/۳۰ | `redirectGuestsTo(auth.login)` در bootstrap/app.php | route با نام `login` نداریم؛ مهمان خطای ۵۰۰ می‌گرفت | تعریف alias `login` | 06B |
| ۱۴۰۵/۰۸/۳۰ | `tasks.competition_id` در 12F اضافه شود، نه 08 | جدول competitions هنوز نیست | ستون بدون FK در 08 | 08 |
| ۱۴۰۵/۰۸/۳۰ | `tasks.ai_recommendation_id` بدون FK تا Step 18 | جدول هنوز نیست | — | 08 |
| ۱۴۰۵/۰۸/۳۰ | ساب‌تسک فقط یک سطح؛ فقط `done` پیش‌نیاز را آزاد می‌کند | سادگی و پیش‌بینی‌پذیری | عمق نامحدود | 08 |

### تصمیم‌های باز
- [ ] سرویس پیامک ایرانی (Step 02/13)
- [ ] RAG داخل Laravel یا FastAPI (Step 15)
- [ ] کتابخانه PDF فارسی (Step 14)
- [x] **06B:** `/system` — cherry-pick + Gate (انجام شد)
- [x] **06B:** ناسازگاری PHP — قفل `platform.php=8.3.0` (انجام شد؛ بعد از اعمال پچ باید `composer update` اجرا و `composer.lock` کامیت شود)
- [ ] **08c:** Kanban با SortableJS (پکیج جدید) یا دکمه‌های انتقال؟ (پیشنهاد: SortableJS، دلیل: UX موبایل)
- [ ] **08a:** سقف حجم پیوست وظایف (پیشنهاد: ۵MB هر فایل؛ ۲۰MB کل رویداد برای حساب عادی)

---

## ۵. Manifest فایل‌های مهم مخزن

### Auth / Account (02–03)
- OtpService, SmsGateway (+Log/Null)، PhoneLogin, VerifyOtp, CompleteProfile
- AccountType, EntitlementService, `config/plans.php`, SelectAccountType, AccountPage
- Middleware `EnsureAccountTypeSelected` (alias `account.type`)، `LimitExceededException`

### Organizations (04)
- Organization, OrganizationInvitation, OrgRole, OrganizationType
- OrganizationService, OrganizationPolicy
- Livewire Orgs: OrganizationIndex, OrganizationForm, OrganizationShow

### Events (05–06)
- Enums: EventStatus, EventType, EventScale, MainGoalType, GoalKind, EventRole
- Models: Event, EventGoal, EventStakeholder, EventInvitation, ActivityLog
- Services: EventService, EventMemberService
- Policy: EventPolicy
- Livewire: EventIndex, EventForm (wizard)، EventShow (هاب + تب اعضا)
- Trait: `App\Support\LogsActivity`
- Migrations: `2026_11_20_000004_create_events_tables`, `2026_11_20_000005_create_event_members_tables`
- `lang/fa/event.php`

### Committees (07)
- Enums: CommitteeType, CommitteeRole
- Model: Committee (softDeletes)؛ Policy: CommitteePolicy؛ Service: CommitteeService
- Notification: AddedToCommittee (database)
- Livewire: EventCommittees + `event-committees.blade.php`
- Migrations: `2026_11_20_000006_create_committees_tables`, `2026_11_21_000007_alter_committees_add_soft_deletes`, `2026_11_21_000008_create_notifications_table`
- `lang/fa/committee.php`؛ تست: `tests/Feature/Committees/CommitteeTest.php`

### Security (06B)
- Migrations: `2026_11_22_000009_add_is_super_admin_to_users_table`, `2026_11_22_000010_hash_event_invitation_tokens`
- Gate `access-admin` (AppServiceProvider)؛ `User::isSuperAdmin()`؛ `UserFactory::superAdmin()`
- Command: `app/Console/Commands/MakeAdmin.php` (`user:make-admin {phone} {--revoke}`)
- Livewire: `System\SystemDashboard` + `livewire/system/system-dashboard.blade.php`
- `EventInvitation::hashToken()/findByToken()`، `EventMemberService::INVITES_PER_HOUR`
- `phpstan-baseline.neon` (خالی؛ در صورت خطای Larastan با `--generate-baseline` پر شود)

### UI (UI و UI-2)
- layouts: public, app, admin, guest (**public و app = قالب جدید UI-2**؛ admin/guest قدیمی)
- Blade components: button, input, textarea, select, card, badge, modal, toast, empty-state, pagination, confirm-dialog, page-header, date-picker + **UI-2:** brand-mark, lane-rope, lanes, event-row
- `resources/css/app.css` (توکن‌های pool/ink/flag/signal + alias های قدیمی)، `resources/js/app.js` (x-reveal, x-inview, counter, countdown)
- Livewire: App\Dashboard (جدید)، Admin\Dashboard (قدیمی، **بدون gate**)
- `public/home.blade.php` (لندینگ جدید)

### تست‌های موجود
- Feature: Account/EntitlementTest, Auth/OtpLoginTest, Committees/CommitteeTest, Orgs/OrganizationTest, ExampleTest
- **06B:** Security/RouteAccessTest, Security/TenantIsolationTest, Security/MakeAdminCommandTest, Security/InviteRateLimitTest, Events/EventCoreTest, Events/EventMemberTest
- Unit: JalaliTest, PhoneNumberTest, ExampleTest
- نتیجه‌ی اجرای تست‌های 06B هنوز ثبت نشده — امیر ثبت کند.

### مستندات (`docs/`)
- `docs/ai/`: 00-MASTER-CONTEXT, 01-DOMAIN-MODEL, 02-COMPETITIONS, 03-KNOWLEDGE-AND-AI, 10-STEPS-PART1, 11-STEPS-PART2, 90-PROGRESS, PROJECT-OVERVIEW
- `docs/spec/product-roadmap.md` (بخش‌های ۴۵ تا ۵۶ نادیده)
- ⚠️ `docs/reference/competition-engines/` (در MASTER و 02 به آن ارجاع شده) **هنوز در مخزن نیست** — قبل از Step 12B اضافه شود.

### Routeها و مجوزها
(میان‌افزار `account.type` = پروفایل کامل + نوع حساب انتخاب‌شده؛ مجوز واقعی داخل کامپوننت Livewire با `authorize()`؛ اکشن‌های Livewire جداگانه authorize می‌شوند.)

| مسیر | نام | میان‌افزار | مجوز (Policy/Gate) | وضعیت |
|---|---|---|---|---|
| `/` | home | — | عمومی | ✅ |
| `/styleguide` | styleguide | فقط `local` | — | ✅ |
| `/login`, `/verify` | auth.login/verify | guest | — | ✅ |
| `/complete-profile`, `/select-account-type`, `POST /logout` | auth.* | auth | — | ✅ |
| `/app` | app.dashboard | auth, account.type | داده‌ی خود کاربر | ✅ |
| `/account` | account.index | auth, account.type | داده‌ی خود کاربر | ✅ |
| `/organizations` | orgs.index | auth, account.type | `viewAny` | ✅ |
| `/organizations/create` | orgs.create | " | `create` | ✅ |
| `/organizations/{organization}` | orgs.show | " | `view` (+ اکشن‌ها: invite/manageMembers/transferOwnership) | ✅ |
| `/organizations/{organization}/edit` | orgs.edit | " | `update` | ✅ |
| `/events` | events.index | " | `viewAny` | ✅ |
| `/events/create` | events.create | " | `create` (+ سقف active_events) | ✅ |
| `/events/{event}` (uuid) | events.show | " | `view` (+ اکشن‌ها: transition/manageMembers/delete) | ✅ |
| `/events/{event}/edit` | events.edit | " | `update` | ✅ |
| `/events/{event}/committees` | events.committees | " | `view` (redirect به `?tab=committees`) | ✅ |
| `/admin` | admin.dashboard | auth, account.type, `can:access-admin` | Gate `access-admin` (+ `authorize` در mount) | ✅ 06B |
| `/system` | system.dashboard | auth, account.type, `can:access-admin` | Gate `access-admin` (+ `authorize` در mount) | ✅ 06B |

---

## ۶. مشکلات شناخته‌شده

| # | شرح | شدت | وضعیت |
|---|---|---|---|
| 1 | فونت Vazirmatn ممکن است woff2 کامل نباشد | پایین | باز |
| 2 | SMS واقعی انتخاب نشده (driver=log) | متوسط | باز |
| 3 | پذیرش دعوت رویداد از لینک عمومی UI جدا ندارد | پایین | باز |
| 4 | `main` هنوز Step 00 است؛ کار روی زنجیره‌ی step/* است | متوسط | باز — **مرج با PR توسط امیر** (06B-3) |
| 5 | تست‌های Pest برای Events/Members کامل نیست | متوسط | ✅ نوشته شد (06B)؛ اجرا با امیر |
| 6 | `/admin` (و `/system`) بدون gate؛ `users.is_super_admin` وجود ندارد | **بالا (امنیتی)** | ✅ حل شد (06B) |
| 7 | `composer.lock` با `composer.json` هم‌خوان نیست (`hekmatinasser/verta` فقط در json) و بسته‌هایی با نیاز PHP ≥ 8.4 دارد، در حالی که `composer.json`/CI روی 8.3 است → `composer install` در CI احتمالاً خطا می‌دهد | **بالا (CI)** | ✅ pin شد؛ **امیر باید `composer update` بزند و lock را کامیت کند** |
| 8 | `event_invitations.token` خام است ولی `organization_invitations.token_hash` هش | متوسط | ✅ حل شد (06B) |
| 9 | CI: Larastan اجرا نمی‌شود | پایین | ✅ گام اضافه شد؛ اگر خطا داد baseline بسازید |
| 10 | صفحات هاب رویداد/کمیته/سازمان هنوز با قالب قدیمی (alias های indigo/slate)؛ لایوت admin/guest قدیمی | پایین | باز — تدریجی |
| 11 | `committees` ستون `uuid` ندارد (docs قدیمی داشت) | پایین | ثبت‌شده؛ در صورت نیاز |
| 12 | نمایش اعلان‌ها در UI نیست (فقط ذخیره) | پایین | Step 13 |
| 13 | بعد از pull: `composer install`، `npm run build`، `php artisan migrate` | — | یادآوری |
| 14 | دعوت‌های pending رویداد لینک/پیامک ندارند (توکن خام هیچ‌جا ارسال نمی‌شود) | متوسط | Step 13 |
| 15 | `organization_invitations` توکن خام را دور می‌ریزد (فقط هش bcrypt) و `findByToken` ندارد | پایین | Step 13 |

---

## ۷. Changelog

## UPDATE — Step 06B — تثبیت و امنیت پایه — ۱۴۰۵/۰۸/۳۰
Status: DONE (کد و تست نوشته شد؛ **اجرای تست‌ها و مرج توسط امیر**)
Branch: step/06b-hardening (از سر step/08-dashboard-site-template)
Commit: feat(auth): super admin gate, hashed event invitation tokens, security tests and CI hardening
Files created: [MakeAdmin command، System\\SystemDashboard + view، phpstan-baseline.neon، tests/Feature/Security/{RouteAccessTest,TenantIsolationTest,MakeAdminCommandTest,InviteRateLimitTest}.php، tests/Feature/Events/{EventCoreTest,EventMemberTest}.php]
Files modified: [User، UserFactory، EventInvitation، EventMemberService، OrganizationService، AppServiceProvider، Admin\\Dashboard، routes/web.php، bootstrap/app.php، DatabaseSeeder، lang/fa/event.php، lang/fa/org.php، phpstan.neon، composer.json، .github/workflows/ci.yml، docs/ai/90-PROGRESS.md]
Migrations: [2026_11_22_000009_add_is_super_admin_to_users_table، 2026_11_22_000010_hash_event_invitation_tokens]
Routes added: [/system (system.dashboard)؛ /admin حالا با can:access-admin]
Decisions: [Gate access-admin؛ /system با cherry-pick؛ SHA-256 برای توکن دعوت؛ rate-limit ۲۰ در ساعت؛ pin PHP 8.3.0؛ redirectGuestsTo]
Fixes: [مهمان روی صفحات auth خطای ۵۰۰ (نبودن route login)؛ DatabaseSeeder به ستون email اشاره می‌کرد و `migrate:fresh --seed` خطا می‌داد]
Known issues / TODO: [امیر: `composer update` و کامیت lock؛ اجرای `php artisan migrate && php artisan test && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse`؛ ساخت ادمین با `php artisan user:make-admin 09...`؛ مرج PR و تگ v0.07.0 (06B-3)؛ در `step/04-system-dashboard` دیگر کاری نمانده و می‌توان حذفش کرد]
Tests: اجرا نشد (طبق دستور امیر)
Next suggested step: 08a

## UPDATE — Docs — سازماندهی مستندات و ثبت UI-2 / 06B — ۱۴۰۵/۰۸/۳۰
Status: DONE (فقط مستندات؛ کد دست نخورد)
Branch: docs/organize-and-step-06b-spec
Commit: docs: organize docs under docs/ai, record Step 07/UI-2, add 06B spec and split Step 08
Files created: []
Files modified: [docs/ai/00-MASTER-CONTEXT.md (جایگزین نسخه‌ی جدید)، docs/ai/01-DOMAIN-MODEL.md، docs/ai/02-COMPETITIONS.md، docs/ai/03-KNOWLEDGE-AND-AI.md (جابه‌جایی از docs/ به docs/ai/)، docs/ai/10-STEPS-PART1.md، docs/ai/90-PROGRESS.md، docs/ai/PROJECT-OVERVIEW.md]
Migrations: []
Decisions: [قالب = UI-2 و Step 08 = وظایف؛ ترتیب 07 ← 06B ← 08؛ `competition_id` در 12F؛ کد واقعی بر docs برتر]
Known issues / TODO: [docs/reference/competition-engines/ در مخزن نیست؛ ناسازگاری PHP و lock (مشکل ۷)]
Tests: اجرا نشد (فقط مستندات)
Next suggested step: 06B

## UPDATE — UI-2 — بازطراحی قالب «Lane lines» — ۱۴۰۵/۰۸/۳۰
Status: DONE (تست دستی)
Branch: step/08-dashboard-site-template (کامیت‌های `9f994df` … `6511cae`)
Commit: feat(ui): replace dashboard & site templates / replace public home template with Lane lines design
Files created: [components/brand-mark, components/lane-rope, components/lanes, components/event-row]
Files modified: [resources/css/app.css, resources/js/app.js, layouts/app, layouts/public, livewire/app/dashboard.blade, livewire/events/event-index.blade, public/home.blade, App\Livewire\App\Dashboard, App\Livewire\Events\EventIndex]
Migrations: []
New packages: []
Routes added: []
Decisions: [alias های indigo/violet/fuchsia → pool؛ صفحات جدید با توکن‌های pool/ink/flag/signal]
Known issues / TODO: [هاب رویداد، کمیته‌ها، فرم رویداد، سازمان، admin و guest هنوز قدیمی]
Tests: دستی
Next suggested step: 06B

## UPDATE — Step 07 — کمیته‌ها — ۱۴۰۵/۰۸/۳۰
Status: DONE (طبق اعلام امیر در ۱۴۰۵/۰۸/۳۰) — کد کامل؛ نتیجه‌ی `php artisan test` در اینجا ثبت نشده (امیر ثبت کند). دستور: `composer install && php artisan migrate && php artisan test && ./vendor/bin/pint --test`
Branch: step/07-committees (ادغام کامیت `b49446b` «first develope from step 07» + تکمیل)
Files created: [CommitteeService, CommitteePolicy, AddedToCommittee notification, Committee model, CommitteeType/CommitteeRole enums, EventCommittees (Livewire), event-committees.blade, lang/fa/committee.php, tests/Feature/Committees/CommitteeTest.php]
Files modified: [Event.php (committees، roleOf مشتق‌شده، hook انتقال مالکیت)، User.php (committees/events)، EventMemberService (حذف عضو ← خروج از کمیته‌ها)، EventShow (?tab=)، AppServiceProvider (policy)، routes/web.php]
Migrations: [2026_11_20_000006_create_committees_tables (از کامیت b49446b؛ دست‌نخورده)، 2026_11_21_000007_alter_committees_add_soft_deletes (softDeletes + حذف unique(event_id,name))، 2026_11_21_000008_create_notifications_table]
Routes: [/events/{event}/committees ← redirect به تب کمیته‌ها در Hub (۴۰۳ برای غیرعضو)]
Decisions:
  - نقش رویداد رئیس کمیته **مشتق‌شده** است (Event::roleOf): عضو معمولی که رئیس یک کمیته است = committee_head؛ در pivot چیزی نوشته/برگردانده نمی‌شود.
  - کلیدهای CommitteeType همان b49446b است (marketing/technical/medical/volunteers)، نه نام‌های PROJECT-OVERVIEW.
  - یکتایی نام کمیته در سرویس (نادیده‌گرفتن حذف‌شده‌ها)، نه در DB، چون softDeletes.
  - حذف کمیته‌ای که وظیفه دارد ممنوع است (Committee::hasTasks؛ تا Step 08 همیشه false؛ در Step 08 باید تست واقعی اضافه شود).
  - ساخت/ویرایش/حذف/ترتیب کمیته = EventPolicy@update؛ رئیس همان کمیته فقط شرح وظایف و اعضا را مدیریت می‌کند.
  - قالب پیشنهادی هنگام پر شدن سقف متوقف می‌شود (کمیته‌های ساخته‌شده حفظ می‌شوند).
  - اعلان درون‌برنامه با جدول notifications (database channel)؛ پیامک در Step 13.
Known issues: [composer.lock با composer.json هم‌خوان نیست (hekmatinasser/verta فقط در json) ← `composer update hekmatinasser/verta`؛ نمایش اعلان‌ها در UI هنوز نیست (Step 13)]
Next suggested step: 06B سپس 08 — وظایف

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

---

## ۸. روال به‌روزرسانی بعد از هر مرحله

1. فایل‌ها/پچ چت‌بات را اعمال کن (`git apply` / `git am`)
2. دستورها را اجرا کن (`composer install`, `php artisan migrate`, `npm run build`, `php artisan test`, `./vendor/bin/pint --test`)
3. تست دستی انجام بده (عرض ~۳۶۰px)
4. بلوک UPDATE را بچسبان؛ جدول وضعیت، Manifest و جدول «Routeها و مجوزها» را به‌روز کن
5. Commit و push کن؛ پس از CI سبز به `main` مرج کن
