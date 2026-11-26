### Step 12E — ورزش رتبه‌ای (سری‌ها)، جدول مدال، صفحه‌ی عمومی (پیاده‌سازی شد)

**پایه:** `step/13-dashboard-notifications` (شامل 12A–12C). 12D (حذفی/گروهی) توسط مدل دیگری انجام می‌شود؛ 12E عمداً فایل‌های مشترک را کم‌ترین حد لمس می‌کند.

**فایل‌های جدید**
- migration `2026_12_02_000140_create_heats_tables` (heats، heat_results + seed قالب‌ها)، `RankedTemplateSeeder` (`ranked_heats`، `ranked_final`، `ranked_field`)
- مدل‌ها: `Heat`، `HeatResult`؛ enum `HeatResultStatus` (ok/DNS/DNF/DQ)
- موتورهای خالص: `HeatRanker` (رتبه با تساوی ۱،۲،۲،۴)، `HeatAdvancer` (n نفر اول هر سری + بهترین‌های باقی‌مانده)
- Support: `HeatValue` (ورودی زمان/عدد با ارقام فارسی)، `NameMasker` (مخفف‌کردن نام)
- سرویس‌ها: `HeatService`، `MedalTableService`، `PublicLinkService`، `PublicCompetitionService`
- UI: `Competitions\RankedShow`، `Competitions\PublicLinks`، `Events\EventMedals`، کنترلر `PublicCompetitionController`، layout `layouts/share`، نمای `public/competition`
- lang: `ranked.php`، `medals.php`، `publicpage.php`
- تست: `HeatRankerTest`، `HeatAdvancerTest`، `HeatValueTest` (Unit)؛ `RankedHeatsTest`، `MedalTableTest`، `PublicPageTest`، `RankedShowTest` (Feature)؛ helper `tests/Support/RankedTestHelpers.php`

**فایل‌های مشترک که لمس شده‌اند (کوچک؛ هنگام merge با 12D بررسی شود)**
- `routes/web.php`: مسیر عمومی `/c/{token}` (بیرون از auth) و `competitions.ranked` + دو `use`
- `CompetitionShow::mount`: ریدایرکت ورزش `result_type=ranked` به `competitions.ranked` (۶ خط)
- `event-competitions.blade.php`: دو `@livewire` (جدول مدال و لینک‌های عمومی) قبل از `@endif` پایانی

**تصمیم‌ها**
- ستون‌های اضافه نسبت به سند: `heats.result_status/entered_by/confirmed_by` (تأیید دومرحله‌ای)، `heat_results.lane/advanced`.
- رتبه‌ی نهایی: فینالیست‌ها به ترتیب رتبه‌ی فینال؛ حذف‌شدگان بعد از آن‌ها با نتیجه‌ی آخرین مرحله‌ای که بودند؛ DNS/DNF/DQ و منصرف‌شده = `null`.
- تساوی در مرز صعود: همه‌ی مساوی‌ها صعود می‌کنند و در پیام گزارش می‌شود.
- صعود دستی است («ساخت مرحله‌ی بعد»)؛ تا مرحله‌ی بعد نتیجه نگرفته، «بازگرداندن مرحله‌ی بعد» ممکن است؛ ویرایش مرحله‌ی قبل تا آن زمان مسدود است.
- مدال: فقط ورزش‌های `finished`؛ rank ۱/۲/۳ (تساوی = رتبه‌ی مشترک)؛ بدون وابستگی در جدول نمی‌آید (فقط تعدادش)؛ امتیاز وزنی اختیاری در `events.metadata.medals`.
- صفحه‌ی عمومی: کنترلر ساده (بدون Livewire)، `throttle:60,1`، توکن ۴۰ نویسه، ۴۰۴ یکسان برای نامعتبر/غیرفعال/حذف‌شده، هدر noindex، فقط داده‌ی «لیست سفید» (نام/وابستگی/نتیجه‌ی تأییدشده)، رفرش خودکار ۶۰ ثانیه. جدول رده‌بندی فقط برای مرحله‌ی `round_robin`؛ بقیه‌ی فرمت‌ها (حذفی/گروهی از 12D) به‌صورت فهرست دور/بازی نمایش داده می‌شوند.
- مخفف نام فقط برای شرکت‌کننده‌ی انفرادی (`settings.public.abbreviate_names`).

**اجرا نشده:** هیچ تست/CLI/migration اجرا نشده؛ فقط `php -l` (سینتکس).
