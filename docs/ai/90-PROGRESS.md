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
| شاخه‌ی کاری فعلی | `step/08b-08c-tasks-ui` (از سر `step/09-budget` @ `3381cf3`؛ شامل 01 تا 07 + UI-2 + 06B + 08a + 09 + 08b/08c) |
| شاخه‌ی اصلی | `main` — هنوز فقط Step 00؛ **مرج نشده** (مشکل ۴) |
| آخرین مرحله‌ی DONE | **08b + 08c** (کد و تست نوشته شد؛ **اجرای تست‌ها توسط امیر**)، 09، 08a، 06B، 07، UI-2 |
| مرحله‌ی بعدی | **10** (ریسک) |
| تاریخ آخرین به‌روزرسانی | ۱۴۰۵/۰۹/۰۱ (08b/08c) |

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
| 08a | وظایف: دیتابیس و منطق | ✅ (پچ آماده؛ اجرا با امیر) | step/08-tasks | Pest نوشته شد | tasks، وابستگی (DFS)، blocked خودکار، Policy، پیوست خصوصی، Milestone، پیشرفت |
| 08b | وظایف: تب رابط | ✅ (کد و تست نوشته شد؛ اجرا با امیر) | step/08b-08c-tasks-ui | Pest (Livewire) نوشته شد | تب «وظایف»: لیست+فیلتر، فرم، پنل جزئیات (زیروظیفه، پیش‌نیاز، کامنت، پیوست)، نقاط عطف، پیشرفت |
| 08c | Kanban، تقویم، وظایف من، یادآور | ✅ (کد و تست نوشته شد؛ اجرا با امیر) | step/08b-08c-tasks-ui | Pest نوشته شد | کانبان (drag بومی + منوی وضعیت)، تقویم شمسی، صفحه‌ی «وظایف من»، `tasks:remind` + اعلان |
| 09 | بودجه و درآمد | ✅ (کد و تست نوشته شد؛ اجرای تست‌ها و migration با امیر) | step/09-budget | Pest نوشته شد | خطوط، تراکنش، رسید، نسخه‌ی مصوب/قفل، هشدار اضافه‌هزینه، سربه‌سر؛ تب «بودجه» در هاب رویداد |
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
| ۱۴۰۵/۰۹/۰۱ | دسترسی بودجه در `BudgetLinePolicy` (کشف خودکار با قرارداد نام؛ بدون تغییر AppServiceProvider): `manage`/`approve` = `EventPolicy@update`؛ `viewSummary` فقط مجموع برای `committee_head` و `member`؛ `viewer` و غیرعضو هیچ | ماتریس MASTER: ویرایش بودجه فقط مالک/مدیر؛ مشاهده‌ی مجموع یا هیچ | رئیس کمیته با ویرایش کمیته‌ای | 09 |
| ۱۴۰۵/۰۹/۰۱ | «کارشناس مالی» نقش جدا نشد؛ فعلاً با مدیر رویداد یکی است | نقش جدید = تغییر `EventRole` و ماتریس (خارج از دامنه‌ی 09) | نقش `finance` | 09 |
| ۱۴۰۵/۰۹/۰۱ | کلید feature جدید `budget_full` (حساب سازمانی با `*` دارد؛ عادی ندارد)؛ `budget_basic` همان قبلی | بدون تغییر `config/plans.php` | افزودن به plans | 09 |
| ۱۴۰۵/۰۹/۰۱ | نسخه‌ی مصوب: بدون ردیف نسخه = مقدماتی ۱؛ تأیید = snapshot + قفل خطوط؛ `startRevision` یک ردیف `draft` می‌سازد؛ تراکنش واقعی هرگز قفل نمی‌شود؛ `budget_lines.version` = نسخه‌ی کاری هنگام آخرین تغییر | ساده، بدون ستون قفل روی `events` | ستون `locked` روی رویداد | 09 |
| ۱۴۰۵/۰۹/۰۱ | هشدار اضافه‌هزینه: واقعی > برنامه + آستانه٪ (دقیقاً ۱۰٪ هشدار نیست)؛ پیش‌فرض `config/budget.php`، override هر رویداد در `events.metadata.budget.overrun_threshold_percent` | «قابل تنظیم» بدون migration روی events | ستون جدید | 09 |
| ۱۴۰۵/۰۹/۰۱ | سربه‌سر = ceil((هزینه‌ی ثابت − درآمد ثابت) × N ÷ (درآمد متغیر − هزینه‌ی متغیر)) با N = `expected_participants`؛ فقط وقتی داده کافی است، وگرنه «داده کافی نیست» | سند: سربه‌سر فقط با داده کافی | حدس/پیش‌فرض | 09 |
| ۱۴۰۵/۰۹/۰۱ | رسید روی دیسک خصوصی `local` (حداکثر ۵MB؛ pdf/jpg/png؛ فقط حساب سازمانی)؛ دانلود با اکشن Livewire دارای `authorize` (route جدید نداریم) | بدون تغییر `routes/web.php` | route دانلود | 09 |
| ۱۴۰۵/۰۹/۰۱ | `budget_lines` و `transactions` softDelete؛ حذف خط دارای تراکنش ممنوع؛ دسته‌ی `other_revenue` به ۱۲ دسته‌ی سند اضافه شد | قانون ۱۱ و نیاز به دسته‌ی عمومی درآمد | حذف سخت | 09 |
| ۱۴۰۵/۰۹/۰۱ | شماره‌ی migration بودجه ۲۰ (`2026_11_25_000020`) | هم‌پیشوند نشدن با Step 08 که هم‌زمان توسط مدل دیگر نوشته می‌شود | ۱۱ | 09 |

| ۱۴۰۵/۰۹/۰۱ | `blocked` فقط خودکار؛ دستی قابل‌انتخاب نیست؛ هنگام وجود پیش‌نیاز ناتمام فقط `cancelled` مجاز است | قاعده‌ی «blocked خودکار» سند | blocked دستی با دلیل | 08a |
| ۱۴۰۵/۰۹/۰۱ | آزادشدن blocked ← `todo` (وضعیت قبلی `doing` ذخیره نمی‌شود) | سادگی | ستون وضعیت قبلی | 08a |
| ۱۴۰۵/۰۹/۰۱ | پیش‌نیاز **حذف‌شده (soft delete)** مانع نمی‌شود؛ پیش‌نیاز **لغوشده** همچنان مانع است | حذف = برداشتن صریح؛ لغو ≠ انجام | هر دو آزاد کنند | 08a |
| ۱۴۰۵/۰۹/۰۱ | وظیفه‌ی done/cancelled با افزودن پیش‌نیاز جدید دوباره blocked نمی‌شود | تاریخچه‌ی انجام‌شده حفظ شود | بازگشایی خودکار | 08a |
| ۱۴۰۵/۰۹/۰۱ | حذف والد ← soft delete زیروظیفه‌ها؛ زیروظیفه کمیته‌ی والد را به ارث می‌برد | یکپارچگی | یتیم‌ماندن | 08a |
| ۱۴۰۵/۰۹/۰۱ | درصد پیشرفت فقط از وظایف «برگ» (بدون زیروظیفه) و بدون لغوشده‌ها | شمارش دوباره نشود | شمارش همه | 08a |
| ۱۴۰۵/۰۹/۰۱ | مجوز وظیفه: ساخت/ویرایش/وابستگی = سطح مدیریت یا رئیس همان کمیته؛ حذف فقط سطح مدیریت؛ تغییر وضعیت و پیوست = مدیریت/رئیس کمیته/assignee؛ viewer هیچ‌چیز؛ کامنت = همه‌ی اعضای غیر‌viewer | ماتریس 01-DOMAIN §10 + فرض‌های صریح (⚠️ کامنت viewer را امیر تأیید کند) | viewer هم کامنت بگذارد | 08a |
| ۱۴۰۵/۰۹/۰۱ | به `viewer` نمی‌شود وظیفه سپرد؛ با حذف عضو از رویداد، assignee وظایفش `null` می‌شود | assignee باید عضو فعال باشد | نگه‌داشتن | 08a |
| ۱۴۰۵/۰۹/۰۱ | سقف پیوست: ۵MB هر فایل (`config/tasks.php`)؛ ۲۰MB کل رویداد برای حساب عادی (`plans.php` ← `attachment_mb_per_event`)؛ نوع‌های مجاز لیست سفید (بدون svg/html/zip) | پیشنهاد سند، تأیید ضمنی با دستور ادامه | بدون سقف | 08a |
| ۱۴۰۵/۰۹/۰۱ | پیوست‌ها روی دیسک خصوصی با نام تصادفی؛ دانلود فقط از route با `TaskPolicy@view` + scoped bindings + `nosniff` | جلوگیری از نشت/XSS | دیسک public | 08a |
| ۱۴۰۵/۰۹/۰۱ | ستون‌های افزوده نسبت به سند: `tasks.created_by`، `task_attachments.uuid`، `milestones.description/reached_at`؛ `priority`=low/medium/high/urgent؛ `milestones.status`=pending/reached/cancelled | سند مقدار نداده بود | — | 08a |

| ۱۴۰۵/۰۹/۰۱ | کانبان **بدون پکیج جدید**: کشیدن-رهای بومی HTML5 با Alpine (دسکتاپ) + منوی «انتقال به» روی هر کارت (موبایل/لمسی)؛ ستون «منتظر پیش‌نیاز» قابل‌رها نیست (خودکار) | قانون «کم‌وابستگی»؛ بدون تغییر `package.json/lock`؛ موبایل با منو پوشش داده می‌شود | SortableJS | 08c |
| ۱۴۰۵/۰۹/۰۱ | لیست/کانبان/تقویم سه «نما» از **یک** کامپوننت `EventTasks` با فیلتر مشترک؛ پنل جزئیات یک drawer؛ پیوند مستقیم `?tab=tasks&task=<uuid>` | کمترین پیچیدگی ارتباط بین کامپوننت‌ها | کامپوننت جدا برای هر نما | 08b/08c |
| ۱۴۰۵/۰۹/۰۱ | موعد وارد‌شده (شمسی، فقط تاریخ) = **پایان همان روز** (۲۳:۵۹) تا وظیفه‌ی «امروز» از ۰۰:۰۱ تأخیردار نشود؛ شروع = ابتدای روز | تجربه‌ی درست | ساعت ۰۰:۰۰ | 08b |
| ۱۴۰۵/۰۹/۰۱ | تقویم شمسی با `JalaliCalendar` (فقط `Jalali::parse/format`؛ کبیسه با رفت‌وبرگشت parse/format) و شروع هفته شنبه؛ نقاط عطف هم روی تقویم می‌آیند | بدون فرض API ناشناخته‌ی Verta | الگوریتم کبیسه‌ی دستی | 08c |
| ۱۴۰۵/۰۹/۰۱ | «وظایف من» = صفحه‌ی `/tasks` (route `tasks.mine`)؛ فقط وظایف assignee=خود کاربر در رویدادهای `visibleTo`؛ لینک در منوی اصلی (نوار پایین موبایل ۵ ستون شد) | سند 08c | تب در داشبورد | 08c |
| ۱۴۰۵/۰۹/۰۱ | یادآور: دستور `tasks:remind` (روزانه ۰۸:۰۰) — فقط `todo/doing` با assignee فعال و رویداد فعال؛ «نزدیک موعد» یک‌بار برای هر موعد، «دارای تأخیر» هر ۳ روز؛ ضد-تکرار با `Cache::add` (بدون migration)؛ کانال فقط `database` تا Step 13 | بدون ستون جدید؛ SMS در Step 13 | ستون `reminded_at` | 08c |
| ۱۴۰۵/۰۹/۰۱ | محدودیت‌های لیست: حداکثر ۳۰۰ ردیف (`config/tasks.php` ← `list_limit`) با پیام؛ رویداد بایگانی/لغوشده فقط‌خواندنی (UI + اکشن‌ها) | کارایی و قانون وضعیت رویداد | pagination | 08b |
| ۱۴۰۵/۰۹/۰۱ | کانبان/تقویم/«وظایف من» برای همه‌ی پلن‌ها (بدون گیت ویژگی) | سند محدودیتی ننوشته | گیت `tasks_full` | 08c |

### تصمیم‌های باز
- [ ] سرویس پیامک ایرانی (Step 02/13)
- [ ] RAG داخل Laravel یا FastAPI (Step 15)
- [ ] کتابخانه PDF فارسی (Step 14)
- [x] **06B:** `/system` — cherry-pick + Gate (انجام شد)
- [x] **06B:** ناسازگاری PHP — قفل `platform.php=8.3.0` (انجام شد؛ بعد از اعمال پچ باید `composer update` اجرا و `composer.lock` کامیت شود)
- [x] **08c:** Kanban — بدون پکیج (drag بومی + منوی وضعیت)؛ اگر UX لمسی کافی نبود، SortableJS بعداً اضافه شود
- [x] **08a:** سقف حجم پیوست وظایف — با پیشنهاد سند پیاده شد (۵MB / ۲۰MB)؛ قابل تغییر در `config/tasks.php` و `config/plans.php`
- [ ] **08a:** آیا `viewer` اجازه‌ی کامنت داشته باشد؟ (فعلاً نه)
- [ ] **13:** اعلان «به شما وظیفه سپرده شد» + ارسال SMS یادآورها (فعلاً فقط اعلان درون‌برنامه‌ای برای یادآور)

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

### Tasks (08a)
- Enums: TaskStatus, TaskPhase, TaskModule, TaskPriority, TaskSource, MilestoneStatus
- Models: Task (softDeletes، uuid)، TaskComment، TaskAttachment (uuid)، Milestone؛ روابط `Event::tasks()/milestones()`، `Committee::tasks()`
- Services: TaskService (CRUD، transition، وابستگی، کامنت، پیوست، پیشرفت)، MilestoneService
- Support: `DependencyGraph` (DFS خالص، بدون DB)
- Policies: TaskPolicy، MilestonePolicy (ثبت در AppServiceProvider)
- Controller: TaskAttachmentController (دانلود خصوصی)
- Config: `config/tasks.php`، `plans.php` ← `attachment_mb_per_event`
- Migration: `2026_11_23_000011_create_tasks_tables` (tasks، task_dependencies، task_comments، task_attachments، milestones)
- `lang/fa/task.php`؛ `Committee::hasTasks()` به جدول واقعی وصل شد؛ `EventMemberService::removeMember` وظایف عضو را unassign می‌کند
- تست‌ها: `tests/Feature/Tasks/{TaskCoreTest,TaskDependencyTest,TaskPolicyTest,TaskAttachmentTest,MilestoneTest}.php`، `tests/Unit/DependencyGraphTest.php`؛ کمک‌تابع‌های `tk_*` در `tests/Pest.php`

### Tasks UI (08b/08c)
- Livewire: `Events/EventTasks` (لیست/کانبان/تقویم، فرم، drawer جزئیات، نقاط عطف)، `Tasks/MyTasks`
- Views: `livewire/events/event-tasks.blade.php`، `livewire/tasks/my-tasks.blade.php`؛ تب `tasks` در `event-show.blade.php`؛ لینک «وظایف من» در `layouts/app.blade.php`
- Support: `JalaliCalendar`
- Console/Notifications: `tasks:remind` (`app/Console/Commands/RemindTasks.php`)، `TaskDueReminder`؛ زمان‌بندی در `routes/console.php`
- Config: `tasks.php` ← `reminder_days`، `list_limit`؛ متن‌ها: `lang/fa/task.php` ← `ui.*`
- تست‌ها: `Tasks/EventTasksComponentTest`، `Tasks/MyTasksTest`، `Tasks/RemindTasksTest`، `Unit/JalaliCalendarTest`؛ `tk_blocked` در `tests/Pest.php`

### Security (06B)
- Migrations: `2026_11_22_000009_add_is_super_admin_to_users_table`, `2026_11_22_000010_hash_event_invitation_tokens`
- Gate `access-admin` (AppServiceProvider)؛ `User::isSuperAdmin()`؛ `UserFactory::superAdmin()`
- Command: `app/Console/Commands/MakeAdmin.php` (`user:make-admin {phone} {--revoke}`)
- Livewire: `System\SystemDashboard` + `livewire/system/system-dashboard.blade.php`
- `EventInvitation::hashToken()/findByToken()`، `EventMemberService::INVITES_PER_HOUR`
- `phpstan-baseline.neon` (خالی؛ در صورت خطای Larastan با `--generate-baseline` پر شود)

### Budget (09)
- Enums: BudgetLineType, BudgetCategory, CostBehavior, BudgetVersionStatus
- Models: BudgetLine (softDeletes، uuid)، Transaction (softDeletes، uuid)، BudgetVersion
- Service: BudgetService؛ Policy: BudgetLinePolicy (کشف خودکار)؛ Support: `App\Support\Money`
- Livewire: `Events\EventBudget` + `livewire/events/event-budget.blade.php` (تب «بودجه» در `event-show.blade.php`)
- `config/budget.php`؛ `lang/fa/budget.php`
- Migration: `2026_11_25_000020_create_budget_tables`
- تست: `tests/Feature/Budget/{BudgetServiceTest,BudgetAccessTest}.php`، `tests/Unit/MoneyTest.php`

### UI (UI و UI-2)
- layouts: public, app, admin, guest (**public و app = قالب جدید UI-2**؛ admin/guest قدیمی)
- Blade components: button, input, textarea, select, card, badge, modal, toast, empty-state, pagination, confirm-dialog, page-header, date-picker + **UI-2:** brand-mark, lane-rope, lanes, event-row
- `resources/css/app.css` (توکن‌های pool/ink/flag/signal + alias های قدیمی)، `resources/js/app.js` (x-reveal, x-inview, counter, countdown)
- Livewire: App\Dashboard (جدید)، Admin\Dashboard (قدیمی، **بدون gate**)
- `public/home.blade.php` (لندینگ جدید)

### تست‌های موجود
- Feature: Account/EntitlementTest, Auth/OtpLoginTest, Committees/CommitteeTest, Orgs/OrganizationTest, ExampleTest
- **08b/08c:** Tasks/EventTasksComponentTest, Tasks/MyTasksTest, Tasks/RemindTasksTest, Unit/JalaliCalendarTest؛ `Security/RouteAccessTest` ← `tasks.mine`
- **08a:** Tasks/* (5 فایل) + Unit/DependencyGraphTest؛ تست `CommitteeTest` («حذف کمیته‌ی دارای وظیفه») با جدول واقعی بازنویسی شد
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
| `/events/{event}?tab=budget` | events.show (تب) | " | `view` برای ورود؛ داخل `EventBudget`: `viewSummary` (مجموع) / `manage` و `approve` (جزئیات و اکشن‌ها) از `BudgetLinePolicy`؛ هر اکشن Livewire جداگانه authorize و شناسه‌ها فقط داخل همین رویداد | ✅ 09 |
| `/tasks` | tasks.mine | auth, account.type | فقط وظایف خود کاربر؛ `TaskPolicy@transition` برای تغییر وضعیت | ✅ 08c |
| `/events/{event}?tab=tasks[&task=uuid]` | events.show (تب) | " | `view` برای ورود؛ داخل `EventTasks`: هر اکشن با `TaskPolicy`/`MilestonePolicy` | ✅ 08b |
| `/events/{event}/tasks/{task}/attachments/{attachment}` | tasks.attachments.download | auth, account.type (+ scoped bindings) | `TaskPolicy@view` (۴۰۳ بیرونی‌ها؛ ۴۰۴ ترکیب نادرست رویداد/وظیفه/پیوست) | ✅ 08a |
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

## UPDATE — Step 08b + 08c — رابط وظایف، کانبان، تقویم، وظایف من، یادآور — ۱۴۰۵/۰۹/۰۱
Status: DONE (کد و تست نوشته شد؛ **اجرای تست‌ها توسط امیر**)
Branch: step/08b-08c-tasks-ui (از سر step/09-budget @ 3381cf3)
Commit: feat(tasks): tasks UI, kanban, calendar, my tasks and reminders
Files created: [Livewire/Events/EventTasks، Livewire/Tasks/MyTasks، دو view، JalaliCalendar، RemindTasks، TaskDueReminder، تست‌های Tasks/EventTasksComponentTest + MyTasksTest + RemindTasksTest، Unit/JalaliCalendarTest]
Files modified: [event-show.blade.php (تب tasks)، layouts/app.blade.php (منو)، routes/web.php (tasks.mine)، routes/console.php (schedule)، config/tasks.php، lang/fa/task.php، lang/fa/event.php، tests/Pest.php (tk_blocked)، Security/RouteAccessTest، docs/ai/90-PROGRESS.md]
Migrations: [— (هیچ migration جدیدی لازم نبود)]
Routes added: [tasks.mine — `/tasks`]
Decisions: [ردیف‌های 08b/08c در Decision Log]
Known issues / TODO: [اعلان assignment و SMS در Step 13؛ کانبان لمسی با منوی وضعیت (SortableJS در صورت نیاز)؛ `pint`/`phpstan`/تست اجرا نشد؛ ویوهای Blade کامپایل/اجرا نشد (فقط توازن دستورها بررسی شد)؛ تست‌های Livewire فایل‌آپلود حساس به تشخیص MIME‌اند]
Tests: اجرا نشد (طبق دستور امیر)؛ lint سینتکس PHP همه‌ی فایل‌ها انجام شد
Next suggested step: 10 — ریسک


## UPDATE — Step 09 — بودجه و درآمد — ۱۴۰۵/۰۹/۰۱
Status: DONE (کد و تست نوشته شد؛ **اجرای migration، تست‌ها و Pint/Larastan توسط امیر**)
Branch: step/09-budget (روی سر `step/08-stadium-landing` @ d30f43d؛ شامل 01 تا 07 + UI-2 + 06B + 08a + قالب stadium)
Commit: feat(budget): budget lines, transactions and versions
Files created: [config/budget.php، lang/fa/budget.php، app/Enums/{BudgetLineType,BudgetCategory,CostBehavior,BudgetVersionStatus}.php، app/Models/{BudgetLine,Transaction,BudgetVersion}.php، app/Policies/BudgetLinePolicy.php، app/Services/BudgetService.php، app/Support/Money.php، app/Livewire/Events/EventBudget.php، resources/views/livewire/events/event-budget.blade.php، tests/Feature/Budget/{BudgetServiceTest,BudgetAccessTest}.php، tests/Unit/MoneyTest.php]
Files modified: [resources/views/livewire/events/event-show.blade.php (یک @elseif برای تب budget)، docs/ai/90-PROGRESS.md]
Migrations: [2026_11_25_000020_create_budget_tables (budget_lines، transactions، budget_versions)]
New env keys: []
New packages: []
Routes added: [] (تب داخل هاب رویداد؛ دانلود رسید = اکشن Livewire)
Decisions: [BudgetLinePolicy؛ نسخه/قفل؛ آستانه‌ی هشدار؛ سربه‌سر؛ رسید خصوصی؛ بدون نقش «کارشناس مالی»؛ feature `budget_full` — جزئیات در Decision Log]
Known issues / TODO: [«کارشناس مالی» نقش جدا ندارد؛ رسید حذف‌شده‌ی تراکنش برای ممیزی روی دیسک می‌ماند؛ هاب/لایوت قدیمی هنوز alias رنگ‌هاست (تب بودجه با توکن‌های UI-2 نوشته شده)؛ خروجی PDF/Excel بودجه در Step 14؛ ایمپورت اکسل بودجه انجام نشد؛ AI→BudgetLine در Step 18]
Tests: اجرا نشد (طبق دستور امیر)
Next suggested step: 10 — ریسک (و 08b/08c توسط مدل دیگر)
## UPDATE — Step 08a — وظایف: دیتابیس و منطق — ۱۴۰۵/۰۹/۰۱
Status: DONE (کد و تست نوشته شد؛ **اجرای مایگریشن و تست‌ها توسط امیر**)
Branch: step/08-tasks (از سر step/08-dashboard-site-template @ d2d65ea)
Commit: feat(tasks): tasks, dependencies and policy
Files created: [6 enum، Task/TaskComment/TaskAttachment/Milestone، TaskService، MilestoneService، DependencyGraph، TaskPolicy، MilestonePolicy، TaskAttachmentController، config/tasks.php، lang/fa/task.php، migration 000011، تست‌های Tasks/* و Unit/DependencyGraphTest]
Files modified: [Event، Committee (hasTasks)، AppServiceProvider، EventMemberService، routes/web.php، config/plans.php، lang/fa/account.php، tests/Pest.php، tests/Feature/Committees/CommitteeTest.php، docs/ai/90-PROGRESS.md]
Migrations: [2026_11_23_000011_create_tasks_tables]
Routes added: [tasks.attachments.download — TaskPolicy@view]
Decisions: [ردیف‌های 08a در Decision Log]
Known issues / TODO: [UI در 08b؛ اعلان assignment؛ تأیید امیر درباره‌ی کامنت viewer؛ `./vendor/bin/pint` و `phpstan` اجرا نشد (فرمت import ها ممکن است نیاز به `pint` داشته باشد)]
Tests: اجرا نشد (طبق دستور امیر) — فقط الگوریتم خالص DependencyGraph مستقل با PHP 8.3 بررسی و lint سینتکس همه‌ی فایل‌ها انجام شد
Next suggested step: 08b

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
