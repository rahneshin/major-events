# 10 — پرامپت مراحل ۰۰ تا ۱۰ (هسته‌ی سامانه) + 06B + UI-2

> **به چت‌بات:** وقتی امیر گفت «مرحله NN را اجرا کن»، فقط بخش همان مرحله را اجرا کن و خروجی را طبق قالب بخش ۴ از `00-MASTER-CONTEXT.md` بده. هر مرحله فرض می‌کند مراحل قبلی در `90-PROGRESS.md` با وضعیت DONE ثبت شده‌اند؛ اگر نیستند، متوقف شو و بگو.

---

## STEP 00 — نصب Laravel، Git و محیط توسعه

**هدف:** یک پروژه‌ی Laravel تمیز، اجراشدنی روی ماشین امیر، متصل به PostgreSQL، پوش‌شده در Git.

**پیش‌نیاز:** PHP 8.3+، Composer، Node 20+، Git، PostgreSQL محلی (یا Docker). از امیر بپرس: سیستم‌عامل (Windows/Mac/Linux)، و آیا از Laragon/Herd/Docker/Sail استفاده می‌کند؛ فقط **یک** مسیر نصب را انتخاب و کامل توضیح بده.

**کارها:**
1. دستور نصب پروژه با `composer create-project laravel/laravel <name>` (بدون starter kit؛ احراز هویت را خودمان می‌سازیم). نسخه‌ی نصب‌شده را در PROGRESS ثبت کن.
2. تنظیم `.env`: `APP_NAME`, `APP_LOCALE=fa`, `APP_FALLBACK_LOCALE=en`, `APP_TIMEZONE=Asia/Tehran`, اتصال `DB_CONNECTION=pgsql` و سایر مقادیر. `.env.example` را هم با مقادیر نمونه (بدون راز) به‌روز کن.
3. ساخت دیتابیس و اجرای `php artisan migrate`.
4. نصب ابزارهای توسعه: Pest، Pint، Larastan (`--dev`). پیکربندی `phpstan.neon`، `pint.json`.
5. نصب Livewire و Tailwind (طبق نسخه‌ی Laravel؛ اگر Vite پیش‌فرض است همان را حفظ کن).
6. ساخت `docs/ai/` و `docs/spec/` و کپی فایل‌های راهنما.
7. `README.md` با دستور راه‌اندازی.
8. `.gitignore` (شامل `.env`, `vendor`, `node_modules`, `storage/*.key`, `/public/storage`).
9. اولین کامیت و اتصال به مخزن راه دور (GitHub/GitLab): دستورهای `git init`, `git branch -M main`, `git remote add origin …`, `git push -u origin main`. روش احراز (SSH key یا token) را توضیح بده.
10. ساخت شاخه‌ی `develop` (اختیاری) یا کار مستقیم با `step/NN-*`.
11. ساخت GitHub Actions ساده: اجرای Pint + Pest روی هر push (`.github/workflows/ci.yml`) با سرویس PostgreSQL.

**خروجی:** دستورهای دقیق، فایل‌های پیکربندی کامل، `README.md`, `ci.yml`.

**پذیرش:** `php artisan serve` صفحه‌ی خوش‌آمد را نشان دهد؛ `php artisan test` سبز؛ مخزن Git پوش‌شده؛ CI سبز.

**کامیت:** `chore: bootstrap laravel project with tooling`

---

## STEP 01 — زیرساخت UI، RTL، تاریخ شمسی، ساختار کد

**هدف:** پوسته‌ی رابط کاربری موبایل‌اول فارسی و کتابخانه‌ی کامپوننت‌های پایه.

**کارها:**
1. تنظیم Tailwind برای RTL و فونت Vazirmatn (فایل‌های فونت در `resources/fonts`، بدون CDN).
2. لایوت‌ها: `layouts/guest.blade.php` و `layouts/app.blade.php` (هدر، منوی موبایل پایین/کشویی، محتوای اصلی، نوار پیام‌ها).
3. کامپوننت‌های Blade: `button`, `input`, `textarea`, `select`, `card`, `badge`, `modal`, `toast`, `empty-state`, `pagination`, `confirm-dialog`, `page-header`.
4. نصب `hekmatinasser/verta`؛ `App\Support\Jalali` با توابع تبدیل/نمایش؛ ورودی تاریخ شمسی (کامپوننت `date-picker` ساده که مقدار میلادی بفرستد).
5. `App\Support\PhoneNumber` (نرمال‌سازی، اعتبارسنجی؛ تست‌های Unit برای: `09123456789`, `+989123456789`, `۰۹۱۲۳۴۵۶۷۸۹`, ورودی نامعتبر).
6. helper تبدیل ارقام فارسی/عربی به لاتین؛ نمایش اعداد فارسی در UI.
7. `lang/fa/` برای پیام‌های عمومی، اعتبارسنجی، و فایل `lang/fa/ui.php`.
8. ساخت ساختار پوشه‌ها طبق بخش ۳.۲ MASTER (فقط پوشه‌های خالی/README لازم).
9. صفحه‌ی نمونه‌ی `/styleguide` (فقط در `local`) برای دیدن کامپوننت‌ها.
10. Error pages (404/403/500/419) فارسی.

**پذیرش:** صفحه‌ی styleguide روی عرض ۳۶۰ درست و راست‌به‌چپ؛ تست Unit نرمال‌سازی موبایل سبز؛ تبدیل تاریخ دوطرفه صحیح.

**کامیت:** `feat(ui): add rtl layout, base components, jalali and phone helpers`

---

## STEP 02 — ورود و ثبت‌نام با موبایل (OTP)

**هدف:** احراز هویت بدون رمز با کد پیامکی.

**پیش‌نیاز:** Step 01. از امیر بپرس: کدام سرویس پیامک ایرانی؟ (تا انتخاب، درایور `log`).

**دیتابیس:**
- `users`: `id`, `uuid`, `name` (nullable)، `phone` (unique, string 16)، `phone_verified_at`, `account_type` (فعلاً nullable؛ Step 03)، `avatar_path` (nullable)، `last_login_at`, `status` (`active|suspended`)، timestamps, softDeletes.
- `otp_codes`: `id`, `phone` (index), `code_hash`, `purpose` (`login`), `expires_at`, `attempts` (default 0), `consumed_at` (nullable), `ip`, `user_agent`, timestamps.

**منطق:**
- `OtpService::send(phone, ip)`: نرمال‌سازی، rate-limit (۱ کد/۶۰ث برای هر شماره، حداکثر ۵ کد/ساعت، حداکثر ۲۰ درخواست/ساعت برای هر IP)، ساخت کد ۶ رقمی امن (`random_int`)، هش با `Hash::make`، ذخیره، ارسال با `SmsGateway`.
- `OtpService::verify(phone, code)`: آخرین کد معتبر، بررسی انقضا و تلاش (حداکثر ۵)، `hash_equals/Hash::check`، مصرف کد، ساخت/یافتن کاربر، `Auth::login`, `session()->regenerate()`.
- `SmsGateway` interface + `LogSmsGateway` + `NullSmsGateway`؛ درایور واقعی در کلاس جدا و با config.
- کاربر جدید پس از اولین ورود به صفحه‌ی «تکمیل پروفایل» (نام) هدایت شود؛ سپس به Step 03 (انتخاب نوع حساب).

**صفحات Livewire:** `Auth\PhoneLogin` (شماره)، `Auth\VerifyOtp` (کد + تایمر ارسال مجدد)، `Auth\CompleteProfile`. دکمه‌ی خروج.

**امنیت:** پیام یکسان برای هر شماره (افشای وجود/عدم وجود کاربر نشود)؛ `throttle` روی route؛ لاگ ورود (جدول `login_events` اختیاری)؛ CSRF؛ پاک‌سازی کدهای منقضی با دستور زمان‌بندی‌شده `otp:prune`.

**تست‌ها:** ارسال کد، تأیید صحیح، کد غلط، انقضا، ۵ تلاش اشتباه، rate-limit، ورود شماره‌ی تازه (ساخت کاربر)، خروج، دسترسی صفحات `auth`.

**پذیرش:** ورود کامل با درایور log (کد در `storage/logs`)؛ تست‌ها سبز؛ کد در DB به‌صورت هش.

**کامیت:** `feat(auth): mobile otp login`

---

## STEP 03 — نوع حساب و Entitlement

**هدف:** دو نوع کاربر و کنترل متمرکز محدودیت‌ها.

**دیتابیس:** `users.account_type` enum `individual | organizational` (not null بعد از onboarding). جدول `account_upgrades` (اختیاری، لاگ ارتقا).

**کارها:**
1. `App\Enums\AccountType`.
2. صفحه‌ی **Onboarding** بعد از اولین ورود: دو کارت «کاربر عادی» و «سازمانی» با خلاصه‌ی امکانات؛ ذخیره‌ی انتخاب.
3. `config/plans.php` با ساختار:
```php
'individual' => [
  'limits' => ['active_events' => 1, 'committees_per_event' => 3, 'members_per_event' => 10,
               'tasks_per_event' => 50, 'organizations' => 0, 'knowledge_ai_requests_per_month' => 0],
  'features' => ['tasks_basic', 'budget_basic', 'export_pdf'],
],
'organizational' => [
  'limits' => ['active_events' => null, 'committees_per_event' => null, ... 'organizations' => 3],
  'features' => ['*'],
],
```
(`null` یعنی نامحدود؛ مقادیر را در PROGRESS به‌عنوان پیش‌فرض ثبت کن تا امیر تغییر دهد.)
4. `EntitlementService`: `can(User $u, string $feature): bool`, `limit(User $u, string $key): ?int`, `remaining(...)`, `assertWithinLimit(...)` که `LimitExceededException` بدهد؛ پیام فارسی + CTA ارتقا.
5. Middleware `EnsureAccountTypeSelected`.
6. صفحه‌ی «حساب من» و **ارتقا از عادی به سازمانی** (در این مرحله بدون پرداخت؛ تغییر نوع با تأیید؛ پرداخت در آینده). کاهش از سازمانی به عادی ممنوع یا مشروط (سقف‌ها را رعایت کند؛ تصمیم را از امیر بپرس).
7. Blade directive/`@feature('export_word')` و helper `limitReached()`.
8. Gate/Policy پایه استفاده از EntitlementService.

**تست‌ها:** مقادیر هر نوع حساب، عبور از سقف، ارتقا، middleware onboarding.

**پذیرش:** کاربر جدید نوع را انتخاب می‌کند؛ `EntitlementService` برای هر دو نوع پاسخ درست می‌دهد؛ تغییر عدد در `plans.php` بدون تغییر کد دیگر اثر می‌گذارد.

**کامیت:** `feat(accounts): account types and entitlement service`

---

## STEP 04 — سازمان مجازی، اعضا و دعوت

**هدف:** کاربر سازمانی بتواند سازمان بسازد، اعضا دعوت و نقش بدهد.

**دیتابیس:**
- `organizations`: `id`, `uuid`, `name`, `slug` (unique), `type` (`federation|club|academy|university|municipality|company|other`)، `description`, `city`, `logo_path`, `owner_id`, `status`, softDeletes.
- `organization_user`: `organization_id`, `user_id`, `role` (`owner|admin|manager|member|viewer`)، `joined_at`، unique(org,user).
- `organization_invitations`: `id`, `organization_id`, `phone`, `role`, `token` (hash), `invited_by`, `expires_at`, `accepted_at`, `status`.

**منطق:**
- فقط `organizational` می‌تواند سازمان بسازد (Entitlement: `organizations`).
- ایجاد سازمان ⇒ سازنده `owner`.
- دعوت با شماره‌ی موبایل: اگر کاربر وجود دارد → اعلان درون‌برنامه + پیامک؛ اگر ندارد → پیامک لینک؛ پذیرش پس از ورود با همان شماره.
- مدیریت اعضا: تغییر نقش، حذف، ترک سازمان (مالک نمی‌تواند ترک کند مگر انتقال مالکیت).
- «سازمان فعال» (Context Switcher) در هدر؛ ذخیره در session و `users.current_organization_id`.
- انتقال مالکیت.

**Policy:** `OrganizationPolicy` (view, update, delete, manageMembers, invite, transferOwnership).

**صفحات:** لیست سازمان‌ها، ساخت/ویرایش، اعضا، دعوت‌ها، تنظیمات.

**تست‌ها:** هر نقش چه می‌تواند بکند؛ کاربر عادی نتواند سازمان بسازد؛ نشت داده (کاربر سازمان B نتواند سازمان A را ببیند)؛ دعوت منقضی؛ دعوت با شماره‌ی دیگر.

**کامیت:** `feat(orgs): organizations, members and invitations`

---

## STEP 05 — رویداد (هسته)

**هدف:** ساخت و مدیریت رویداد برای هر دو نوع کاربر.

**دیتابیس `events`:** `id`, `uuid`, `organization_id` (nullable)، `created_by`, `title`, `slug`, `sport_type`, `event_type` (از جدول `event_types` یا enum: mega, multi_sport, regional, local, youth, para, community, tournament, league, festival, other)، `scale` (`small|medium|large`)، `status` (`draft|planning|ready|running|completed|archived|cancelled`)، `start_date`, `end_date`, `venue_name`, `city`, `province`, `expected_participants`, `expected_spectators`, `budget_estimate` (ریال)، `main_goal_type` (`social|commercial|participation|tourism|performance|mixed`)، `description`, `metadata` (json)، softDeletes.
- `event_goals`: `event_id`, `kind` (`main|secondary`)، `title`, `description`, `priority`, `smart_measure`, `target_value`, `unit`.
- `event_stakeholders`: ساده (نام، نقش، توضیح).

**منطق:**
- ایجاد رویداد: کاربر عادی با `active_events` محدود (Entitlement)، سازمانی داخل سازمان فعال یا شخصی.
- مراحل فرم: اطلاعات پایه ← هدف و اولویت ← مکان/زمان ← خلاصه. ذخیره‌ی پیش‌نویس.
- اعتبارسنجی: تاریخ پایان ≥ شروع، زمان‌ها شمسی→میلادی.
- تغییر وضعیت با قواعد (از `draft` مستقیم به `completed` ممنوع).
- «رویداد فعال» = وضعیت‌های `draft..running`.
- آرشیو و حذف نرم.
- صفحه‌ی خلاصه‌ی رویداد (هاب) با تب‌های آینده: کمیته‌ها، وظایف، بودجه، ریسک، … (تب‌ها در این مرحله فقط placeholder با `@feature`).

**Policy:** `EventPolicy`. فعلاً: سازنده و مالک/مدیر سازمان.

**تست‌ها:** سقف رویداد کاربر عادی، سازمانی چندرویدادی، ایزولاسیون، گذار وضعیت.

**کامیت:** `feat(events): event core with goals and status workflow`

---

## STEP 06 — اعضا و نقش‌های رویداد، دسترسی

**هدف:** افراد دیگر بتوانند در رویداد شرکت کنند.

**دیتابیس:** `event_user`: `event_id`, `user_id`, `role` (`event_owner|event_manager|committee_head|member|viewer`)، `added_by`, unique. `event_invitations` مشابه سازمان.

**منطق:**
- دعوت با موبایل (هم‌خانواده با Step 04: کد مشترک `InvitationService` بسازد/بازآرایی کن).
- در رویداد سازمانی می‌توان از اعضای سازمان انتخاب کرد.
- سقف `members_per_event` از Entitlement.
- `EventPolicy` کامل + `Gate::before` برای مالک/مدیر سازمان.
- Scope کوئری: `Event::visibleTo($user)`.
- تب «اعضا» در هاب رویداد.
- Activity log پایه: جدول `activity_logs` (`user_id`, `event_id`, `subject_type/id`, `action`, `properties` json, `ip`) و trait `LogsActivity`؛ از این مرحله به بعد همه‌ی تغییرات مهم لاگ شود.

**تست‌ها:** ماتریس نقش×عملیات (جدول)، سقف عضو، دعوت.

**کامیت:** `feat(events): event members, roles and activity log`

---

## STEP 07 — کمیته‌ها

**دیتابیس:** `committees`: `id`, `event_id`, `name`, `type` (از لیست پیش‌فرض: اجرایی، مالی، بازاریابی و رسانه، فنی/مسابقات، پزشکی و ایمنی، تدارکات و لجستیک، نیروی انسانی/داوطلبان، حفاظت و امنیت، تشریفات، سفارشی)، `description`, `head_user_id`, `color`, `position`. `committee_user`: `committee_id`, `user_id`, `role` (`head|member`).

**منطق:**
- سقف `committees_per_event` (کاربر عادی ۳).
- قالب پیشنهادی بر اساس `event_type` (کد ثابت؛ بعداً AI پیشنهاد غنی‌تر می‌دهد).
- عضو کمیته باید عضو رویداد باشد (یا هم‌زمان اضافه شود).
- اعلان به عضو جدید (in-app؛ پیامک در Step 13).
- تب «کمیته‌ها»: لیست، افزودن، مرتب‌سازی، شرح وظایف، نمای هر کمیته.

**تست‌ها:** سقف، دسترسی رئیس کمیته، حذف کمیته و اثر روی وظایف (وظایف بدون کمیته نشوند؛ تصمیم: جلوگیری یا انتقال).

**کامیت:** `feat(committees): committees with members and templates`

**✅ وضعیت واقعی پیاده‌سازی (شاخه‌ی `step/07-committees`، تفاوت با متن بالا):**
- `CommitteeType` در کد: `executive, finance, marketing, technical, medical, logistics, volunteers, security, protocol, custom` (نه نام‌های بلند `marketing_media/technical_competitions/...`). **کد مرجع است.**
- جدول `committees` **ستون `uuid` ندارد** (URL با `id` داخلی در کامپوننت؛ route عمومی `/events/{event}/committees` فقط redirect به تب است). در صورت نیاز در Step 08 بررسی شود.
- `softDeletes` + حذف `unique(event_id,name)`؛ یکتایی نام در `CommitteeService` (نادیده‌گرفتن حذف‌شده‌ها).
- نقش «رئیس کمیته» در سطح رویداد **مشتق‌شده** است (`Event::roleOf`)، در pivot نوشته نمی‌شود.
- حذف کمیته‌ای که وظیفه دارد ممنوع است (`Committee::hasTasks()`؛ تا Step 08 همیشه `false`) ← **Step 08 باید تست واقعی اضافه کند.**
- اعلان درون‌برنامه: جدول `notifications` (کانال database)؛ UI نمایش اعلان در Step 13.
- Policy: `CommitteePolicy` (ساخت/ویرایش/حذف/ترتیب = `EventPolicy@update`؛ رئیس همان کمیته: شرح وظایف و اعضا).
- Migrationها: `000006_create_committees_tables`، `000007_alter_committees_add_soft_deletes`، `000008_create_notifications_table`.
- **تست:** `tests/Feature/Committees/CommitteeTest.php` نوشته شده؛ اجرای کامل روی سیستم امیر انجام می‌شود (در PROGRESS ثبت شود).

---

## STEP 06B — تثبیت و امنیت پایه (اجرا بعد از 07 و پیش از 08)

> **چرا این مرحله هست:** در Step 05–06 چند حفره‌ی امنیتی و بدهی فنی ثبت شد (`90-PROGRESS.md` بخش «مشکلات شناخته‌شده»). به دستور مالک محصول، 06B **قبل از Step 08** انجام می‌شود تا وظایف روی پایه‌ی امن ساخته شوند. ترتیب اجرا: 07 ← **06B** ← 08.
> این متن از MASTER (اصل ۵)، `01-DOMAIN-MODEL` و PROGRESS جمع‌بندی شده؛ قبل از کد، با کد واقعی تطبیق بده (قانون ۱۲ MASTER).

**بخش‌ها (پیشنهاد: 06B-1 امنیت، 06B-2 تست و CI، 06B-3 مرج):**

**06B-1 — امنیت و ادمین سیستم**
- migration: `users.is_super_admin` (`boolean`, default `false`, index). مقداردهی فقط با seeder/دستور artisan (`php artisan user:make-admin 09...`)، **هرگز از UI**.
- Gate `access-admin` (یا middleware `super.admin`) روی `/admin` و `/system`؛ کاربر غیرادمین ← ۴۰۳.
- لایه‌ی چهارم دسترسی (سیستم) در `UserFactory` (`->superAdmin()`) و `User::isSuperAdmin()`.
- شاخه‌ی `step/04-system-dashboard` (مسیر `/system`، کامپوننت `System\SystemDashboard`) هنوز در زنجیره‌ی اصلی نیست. **تصمیم باز:** (الف) cherry-pick و قفل با همین Gate، یا (ب) ادغام در `/admin` و حذف شاخه. پیش‌فرض: (الف).
- یکسان‌سازی توکن دعوت: `event_invitations.token` (خام) ← `token_hash` (هش) مثل `organization_invitations`؛ مقایسه با `hash_equals`؛ migration داده‌ی موجود (دعوت‌های pending باطل یا هش شوند).
- بازبینی rate-limit روی routeهای حساس (OTP موجود است؛ دعوت‌ها و جست‌وجوی شماره بررسی شود).

**06B-2 — تست و CI**
- `tests/Feature/Security/RouteAccessTest.php`: ماتریس «هر route غیرعمومی × (مهمان | بدون onboarding | کاربر عادی | عضو | غیرعضو | ادمین)» با انتظار redirect/۴۰۳/۲۰۰.
- تست نشت tenant: کاربر B رویداد/سازمان/کمیته‌ی کاربر A را نبیند (`Event::visibleTo`، `events.show/edit/committees`، `orgs.*`).
- تکمیل تست‌های Events و Members (مشکل شناخته‌شده ۵).
- **CI:** افزودن گام Larastan (سطح ۵)؛ همگام‌سازی `composer.lock` (فعلاً `hekmatinasser/verta` فقط در `composer.json` است؛ `composer update hekmatinasser/verta`).
- **ناسازگاری نسخه‌ی PHP:** `composer.json` و CI روی PHP `^8.3`/`8.3` هستند، اما `composer.lock` بسته‌هایی دارد که PHP ≥ 8.4 می‌خواهند (مثلاً `symfony/http-foundation` v8.1.x). یا CI/محیط به 8.4 ارتقا یابد یا lock با `config.platform.php = 8.3.x` دوباره ساخته شود. **با `php -v` و `composer show` تأیید کن؛ تصمیم با امیر.**

**06B-3 — مرج و نظم شاخه‌ها**
- زنجیره‌ی فعلی (همه در `step/08-dashboard-site-template`): 01 ← 02 ← 03 ← 04 ← UI ← 05 ← 06 ← 07 ← UI-2. مرج به `main` با PR و CI سبز؛ تگ `v0.07.0`.
- جدول «توپولوژی شاخه‌ها» و «Routeها و مجوزها» در PROGRESS را نگه‌دار.

**تست‌ها:** همان بخش 06B-2. **معیار پذیرش:** `/admin` و `/system` برای کاربر عادی ۴۰۳؛ CI سبز (migrate + pint + larastan + test)؛ `main` به‌روز.

**کامیت‌ها:** `feat(auth): add super admin gate for admin and system panels` · `refactor(events): hash event invitation tokens` · `test(security): route access matrix and tenant isolation` · `ci: add larastan and sync composer lock`

---

## STEP UI-2 — بازطراحی قالب «Lane lines» (✅ انجام‌شده؛ شاخه‌ی `step/08-dashboard-site-template`)

> **توضیح تاریخی:** این کار یک بازطراحی ظاهری بود که روی شاخه‌ای با نام `step/08-...` انجام شد و در متن مراحل نبود؛ برای جلوگیری از اشتباه با «Step 08 — وظایف» اینجا به‌عنوان مرحله‌ی UI-2 ثبت شد (مشابه ردیف «UI» قبلی). **شماره‌ی Step 08 همچنان «وظایف» است.**

**هدف:** هویت بصری جدید و یکپارچه (ورزشی/استخر-مسیر شنا) برای سایت عمومی و پنل کاربر.

**تحویل‌دادنی (از کامیت‌های `9f994df` تا `6511cae`):**
- **توکن‌های طراحی** در `resources/css/app.css` (`@theme`): پالت `pool-*` (آبی)، `flag-*` (زرد)، `signal-*` (قرمز هشدار)، `ink-*` (خاکستری-آبی)، `success/warning/danger`؛ فونت Vazirmatn. **نام‌های قدیمی `indigo-*`/`violet-*`/`fuchsia-*` به‌عنوان alias به پالت جدید نگاشت شده‌اند** تا صفحات قدیمی (هاب رویداد، کمیته‌ها، سازمان) بدون تغییر بشکنند؛ در مراحل بعد تدریجی به `pool/ink` مهاجرت شوند.
- **کامپوننت‌های Blade جدید:** `brand-mark`، `lane-rope`، `lanes`، `event-row`.
- **لایوت‌ها:** بازنویسی `layouts/app` و `layouts/public`.
- **صفحات:** `public/home` (لندینگ)، `livewire/app/dashboard`، `livewire/events/event-index` (لیست رویدادها با ردیف و طناب مسیر).
- **Livewire:** به‌روزرسانی `App\Livewire\App\Dashboard` و `Events\EventIndex`.
- **JS (`resources/js/app.js`، بدون وابستگی جدید):** دایرکتیوهای Alpine `x-reveal`، `x-inview`، `counter`، `countdown`؛ احترام به `prefers-reduced-motion`.
- **کلاس‌های CSS:** دکمه‌ها `btn-primary`، `btn-flag`، `btn-ghost`، `btn-ghost-light`؛ سطوح `glass`، `glass-dark`، `card-hover`؛ و `bar-fill`، `skeleton` (ببین `app.css`).

**هنوز با قالب قدیمی:** `event-show` (هاب)، `event-committees`، `event-form`، صفحات سازمان و حساب، لایوت `admin`/`guest`. **قانون برای مراحل بعد:** صفحات جدید (وظایف و …) با توکن‌های `pool/ink/flag/signal` و کلاس‌های `btn-*` نوشته شوند، نه `indigo/slate`.

**تست:** دستی (بدون Pest). **کامیت‌ها:** `feat(ui): replace public home template with Lane lines design` و کامیت‌های هم‌خانواده.

---

## STEP 08 — وظایف، وابستگی، Milestone و تقویم (به سه بخش a/b/c)

> **پیش‌نیاز:** 06B انجام شده باشد (ترتیب: 07 ← 06B ← 08). **UI:** صفحات جدید با توکن‌های قالب UI-2 (`pool/ink/flag/signal`، `btn-primary/btn-flag/btn-ghost`) نوشته شوند.
> **ماتریس دسترسی** (از `01-DOMAIN-MODEL` بخش ۱۰): تغییر وضعیت وظیفه: مالک/مدیر سازمان، `event_owner`، `event_manager` ✅؛ `committee_head` فقط وظایف کمیته‌ی خودش؛ `member` فقط وظیفه‌ی خودش (assignee)؛ `viewer` ❌. کامنت برای همه‌ی اعضا.

### 08a — دیتابیس و منطق
**دیتابیس:**
- `tasks`: `id`, `uuid`, `event_id`, `committee_id` (nullable)، `parent_id` (nullable، فقط یک سطح)، `title`, `description`, `phase` (`pre|during|post`)، `module` (`strategy|operations|budget|marketing|sponsorship|risk|logistics|competitions|evaluation`)، `assignee_id` (nullable)، `status` (`todo|doing|blocked|done|cancelled`)، `priority`, `start_at`, `due_at`, `completed_at`, `estimated_hours`, `source` (`manual|ai`)، `ai_recommendation_id` (nullable، **بدون FK تا Step 18**)، `position`, softDeletes.
  - **`competition_id` (nullable) در این مرحله اضافه نمی‌شود**: جدول `competitions` تا Step 12 وجود ندارد. در migration مرحله‌ی 12F با FK اضافه شود (تصمیم ثبت‌شده).
- `task_dependencies`: `task_id`, `depends_on_task_id`, `type` (`finish_to_start`)، unique جفت.
- `task_comments`، `task_attachments` (دیسک خصوصی `local`، نوع/اندازه محدود؛ دانلود از route دارای Policy).
- `milestones`: `event_id`, `title`, `due_at`, `status`.

**منطق (`TaskService`):**
- سقف `tasks_per_event` (Entitlement)؛ جلوگیری از وابستگی چرخشی (DFS)؛ `blocked` خودکار تا اتمام پیش‌نیازها (فقط `done` پیش‌نیاز را آزاد می‌کند؛ `cancelled` آزاد نمی‌کند).
- `TaskPolicy` طبق ماتریس بالا؛ activity log برای ساخت/تغییر وضعیت/حذف.
- اتصال `Committee::hasTasks()` به جدول واقعی + تست «حذف کمیته‌ی دارای وظیفه ممنوع».
- درصد پیشرفت رویداد/کمیته (سرویس).

**تست‌ها:** چرخه‌ی وابستگی، مجوزها (۴۰۳)، سقف، نشت tenant، تغییر وضعیت و اثر روی وابسته‌ها، `hasTasks`.

> **✅ وضعیت واقعی 08a (شاخه‌ی `step/08-tasks`):** پیاده‌سازی شد؛ جزئیات تصمیم‌ها در `90-PROGRESS.md` (Decision Log، ردیف‌های 08a). `competition_id` و FK برای `ai_recommendation_id` عمداً نیامده‌اند.

### 08b — رابط (تب وظایف)
- لیست با فیلتر (کمیته، وضعیت، مسئول، فاز، موعد)، فرم وظیفه، ساب‌تسک، کامنت، پیوست، Milestone، نشانگر تأخیر (overdue) و مرتب‌سازی. جایگزین placeholder تب «وظایف» در `EventShow`.

### 08c — Kanban، تقویم، «وظایف من» و یادآور
- Kanban (Livewire + Alpine/Sortable؛ پکیج جدید فقط با ثبت دلیل)، تقویم شمسی، نمای «وظایف من» در همه‌ی رویدادها، دستور زمان‌بندی‌شده‌ی `tasks:remind` (پیام در Step 13).

**کامیت‌ها:** `feat(tasks): tasks, dependencies and policy` · `feat(tasks): tasks tab ui` · `feat(tasks): kanban, calendar and reminders`

---

## STEP 09 — بودجه و درآمد

**دیتابیس:**
- `budget_lines`: `event_id`, `type` (`expense|revenue`)، `category` (دسته‌های پیش‌فرض: محل، تجهیزات، نیروی انسانی، حمل‌ونقل، پزشکی، بازاریابی، تعهدات اسپانسر، بلیت/ثبت‌نام، اسپانسر، حقوق رسانه، کالا، متفرقه/احتیاط)، `name`, `cost_behavior` (`fixed|variable`)، `planned_amount`, `committee_id` (nullable)، `assumption` (متن)، `version`.
- `transactions`: `budget_line_id`, `amount`, `date`, `description`, `receipt_path`, `created_by`.
- `budget_versions`: snapshot مصوب.

**منطق:**
- مبلغ‌ها به ریال صحیح؛ نمایش با جداکننده و گزینه‌ی تومان.
- نسخه‌ی مقدماتی ← نسخه‌ی مصوب (قفل با تأیید مدیر).
- مقایسه‌ی برنامه با واقعی؛ هشدار >۱۰٪ اضافه‌هزینه (قابل تنظیم).
- خلاصه: کل درآمد/هزینه/تراز، سربه‌سر فقط وقتی داده کافی است (وگرنه پیام «داده کافی نیست»).
- در کاربر عادی: فقط دسته‌ها و مجموع ساده (`budget_basic`)؛ سازمانی: همه‌ی امکانات، کمیته‌ای، نسخه‌ها.
- دسترسی: فقط مدیر رویداد/کارشناس مالی؛ بقیه فقط مشاهده‌ی مجموع یا هیچ.

**تست‌ها:** محاسبات، دقت اعداد، قفل نسخه، دسترسی.

**کامیت:** `feat(budget): budget lines, transactions and versions`

---

## STEP 10 — ریسک

**دیتابیس:** `risks`: `event_id`, `title`, `category` (ایمنی/پزشکی/جوی/جمعیت/مالی/حقوقی/امنیت/فنی/اعتباری)، `description`, `likelihood` (1–5)، `impact` (1–5)، `score` (محاسبه‌شده)، `response` (`accept|reduce|avoid|transfer`)، `control_actions`, `owner_id`, `status`, `review_date`.
`incidents`: ثبت حادثه‌ی روز رویداد (`event_id`, `risk_id` nullable, `title`, `severity`, `reported_by`, `occurred_at`, `resolution`).

**منطق:**
- ماتریس ۵×۵ تعاملی؛ رنگ‌بندی بر اساس امتیاز.
- چک‌لیست‌های پیشنهادی ثابت (پزشکی، آب‌وهوا، جمعیت، تخلیه) به‌عنوان **قالب** با برچسب «پیشنهاد؛ نیازمند تأیید متخصص».
- ارتباط ریسک ← وظیفه‌ی کنترلی.
- در کاربر عادی: ثبت ساده (`risk_basic`) بدون ماتریس پیشرفته (اختیاری؛ در PROGRESS ثبت کن).

**تست‌ها:** محاسبه‌ی امتیاز، دسترسی، ارتباط وظیفه.

**کامیت:** `feat(risk): risk register and incident log`
