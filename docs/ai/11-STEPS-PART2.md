# 11 — پرامپت مراحل ۱۱ تا ۲۲ (ماژول‌های تکمیلی، پایگاه دانش، AI، استقرار)

> **به چت‌بات:** قواعد `00-MASTER-CONTEXT.md` (به‌ویژه بخش ۸ برای مراحل AI) اجباری است. مراحل ۱۵ تا ۱۸ فقط برای حساب‌های `organizational` فعال می‌شوند (از طریق Entitlement) مگر امیر تصمیم دیگری بگیرد.

---

## STEP 11 — اسپانسرینگ و بازاریابی

**دیتابیس:**
- `sponsors`: `event_id`, `name`, `category`, `contact_name`, `contact_phone`, `status` (`prospect|negotiating|signed|delivered|lost`)، `cash_value`, `in_kind_value`, `notes`.
- `sponsorship_assets`: `event_id`, `name`, `type` (نام‌گذاری، تابلو، محتوای دیجیتال، غرفه، مهمان‌نوازی، رسانه…)، `inventory`, `price`, `description`.
- `sponsor_deliverables`: `sponsor_id`, `asset_id`, `commitment`, `due_at`, `status`, `evidence_path`.
- `marketing_plans`: `event_id`, `target_segments` (json)، `positioning`, `message`, `channels` (json).
- `campaign_items`: `event_id`, `phase` (`pre|during|post`)، `channel`, `title`, `publish_at`, `owner_id`, `status`, `kpi`.

**منطق:** فهرست دارایی‌ها، بسته‌های اسپانسری (تجمیع دارایی‌ها)، پیگیری تعهدات (هشدار موعد)، تقویم محتوا، KPIهای آگاهی/دسترسی/تعامل. لینک تعهد اسپانسر به **وظیفه‌ی روز رویداد**. ماژول فقط برای حساب سازمانی (`sponsorship`).

**تست‌ها:** وضعیت‌ها، تعهدات معوق، دسترسی.

**کامیت:** `feat(sponsorship): sponsors, assets, deliverables and campaign calendar`

---

## STEP 12 — مسابقات (ثبت‌نام، قرعه، برنامه، نتایج)

**اصل فنی:** قرعه، جدول و پیشروی با **الگوریتم قطعی و تست‌شده** است، نه AI.

**دیتابیس:** `competitions` (`event_id`, `name`, `format` = `league|double_league|knockout|group_knockout`, `rules` json)، `teams`/`participants`, `registrations` (`status`, `documents`)، `groups`, `matches` (`round`, `home_id`, `away_id`, `venue`, `starts_at`, `status`, `home_score`, `away_score`, `result_meta`)، `standings` (محاسبه‌شده/cached).

**منطق:**
- ثبت‌نام و تأیید مدارک؛ ظرفیت.
- سیدبندی و قرعه با seed ذخیره‌شده (قابل بازتولید و ممیزی).
- تولید برنامه‌ی دورمحور (round-robin الگوریتم دایره‌ای) و حذفی؛ کنترل تداخل زمان/مکان.
- ثبت نتیجه، جدول، قواعد تساوی (قابل پیکربندی)، پیشروی حذفی.
- ورود نتیجه توسط نقش «کارشناس مسابقات».
- حجم زیاد: صفحه‌بندی، ایندکس مناسب، پردازش دسته‌ای (Job).
- بعداً: import اکسل (Step در آینده/خارج از MVP).

**تست‌ها:** صحت round-robin برای n زوج/فرد، قرعه‌ی تکرارپذیر، جدول، تساوی، پیشروی.

**کامیت:** `feat(competitions): registration, draw, schedule and results engine`

---

## STEP 13 — داشبورد و اعلان‌ها

**کارها:**
1. داشبورد رویداد: پیشرفت وظایف، کمیته‌های عقب‌افتاده، بودجه‌ی مصرف‌شده، ریسک‌های بحرانی، شمارش معکوس، فعالیت اخیر.
2. داشبورد «خانه»: رویدادهای من، وظایف من، دعوت‌ها.
3. **اعلان‌ها:** `notifications` جدول Laravel، کانال‌های `database` و `sms` (از SmsGateway)؛ ترجیحات کاربر (`notification_preferences`)؛ محدودیت ارسال پیامک (هزینه): فقط رویدادهای مهم (تخصیص وظیفه‌ی فوری، دعوت، موعد نزدیک).
4. Jobهای صف‌شده، retry/backoff، لاگ خطا.
5. زنگوله‌ی اعلان با شمارنده و علامت‌گذاری خوانده‌شده.
6. «**مرکز کنترل روز رویداد**» (نسخه‌ی اولیه): وضعیت وظایف فاز `during`, گزارش حادثه، اطلاعیه‌ی سریع به اعضا.

**تست‌ها:** ارسال اعلان، ترجیحات، صف، محدودیت.

**کامیت:** `feat(dashboard): dashboards and notifications`

---

## STEP 14 — خروجی گرفتن

**کارها:** سرویس `ExportService` با خروجی‌های: **PDF** (برنامه‌ی رویداد، وظایف، بودجه، رجیستر ریسک؛ پشتیبانی کامل فارسی/RTL با فونت تعبیه‌شده؛ انتخاب کتابخانه را با امیر تأیید کن: مثلاً mPDF یا Browsershot)، **Excel** (`maatwebsite/excel` یا معادل)، **Word** (`phpoffice/phpword`). قالب‌های گزارش با لوگوی سازمان. خروجی تولیدشده در Job و لینک دانلود موقت (signed URL). Entitlement: کاربر عادی فقط PDF ساده.

**تست‌ها:** تولید فایل، دسترسی، محدودیت حساب. (بررسی چشمی راست‌به‌چپ بودن PDF در تست دستی.)

**کامیت:** `feat(export): pdf, excel and word exports`

---

## STEP 15 — پایگاه دانش: آپلود، شناسنامه، استخراج، کنترل کیفیت

**پیش‌تصمیم (از امیر بپرس و در Decision Log ثبت کن):**
- پردازش اسناد در Laravel یا سرویس Python جدا (FastAPI)؟ (پیشنهاد: سرویس جدا برای استخراج/Embedding با API داخلی.)
- حق آپلود: فقط ادمین سامانه (Knowledge Admin) یا هر سازمان کتابخانه‌ی خودش را هم دارد؟ سیاست مجوز استفاده از کتاب/اسلاید دیگران؟
- کتابخانه‌ی عمومی (مشترک بین سازمان‌ها) در برابر خصوصی سازمان.

**دیتابیس:**
- `knowledge_sources`: `uuid`, `scope` (`global|organization`)، `organization_id`, `title`, `author`, `publisher`, `year`, `edition`, `source_type` (`book|slides|article|regulation|report|template|other`)، `language`, `domain_tags` (json)، `jurisdiction`, `authority_level` (`A|B|C|D`)، `valid_from`, `valid_until`, `license_status`, `review_status` (`pending|approved|rejected`)، `file_path`, `file_hash`, `processing_status`, `uploaded_by`. فیلد ناشناخته = `null`، نه حدس.
- `knowledge_source_versions`.
- `knowledge_pages` (متن استخراج‌شده به تفکیک فصل/صفحه/اسلاید با `flags` json: `irrelevant`, `outdated`, `foreign_jurisdiction`, `extraction_error`).

**کارها:**
1. آپلود چندفرمتی (PDF، PPTX، DOCX، TXT/MD، XLSX)، محدودیت حجم، آنتی‌ویروس/نوع MIME واقعی، ذخیره‌ی خصوصی (نه public).
2. صف پردازش: استخراج متن با حفظ ساختار (فصل/اسلاید/صفحه)، جدول‌ها، OCR برای اسکن (اختیاری فاز بعد).
3. **کنترل کیفیت خودکار:** پرچم اسلاید/صفحه‌ی نامرتبط (مثلاً اسلاید ECG در منبع مدیریت رویداد)، آمار/سال قدیمی، ارجاع به قوانین کشور دیگر؛ همه نیازمند **تأیید انسان** قبل از ورود به ایندکس.
4. UI مدیریت: کتابخانه، جزئیات منبع، فهرست صفحه‌ها، رد/تأیید، چک‌لیست پذیرش (پیوست B سند محصول).
5. **تگ‌گذاری موضوعی** بر اساس ۱۳ فصل (Step 15 جدول `knowledge_topics` با seed از بخش ۸ سند محصول).
6. دسترسی: فقط Knowledge Admin / نقش مجاز.

**تست‌ها:** آپلود، پردازش فایل نمونه، پرچم‌ها، تأیید/رد، دسترسی tenant.

**کامیت:** `feat(knowledge): sources upload, extraction and quality review`

---

## STEP 16 — Chunking، Embedding و جست‌وجوی ترکیبی

**کارها:**
1. **Chunking ساختاری** (نه ثابت): کتاب = فصل←بخش؛ اسلاید = شماره+عنوان+متن؛ مقاله = بخش‌های استاندارد؛ آیین‌نامه = ماده/بند. اندازه‌ی هدف ۳۰۰–۸۰۰ توکن با همپوشانی کم؛ هر chunk یک مفهوم.
2. `knowledge_chunks`: `source_id`, `page_id`, `text`, `heading_path`, `topic_tags`, `sport_type`, `event_type`, `language`, `jurisdiction`, `year`, `authority_level`, `valid_until`, `organization_id/scope`, `embedding` (pgvector)، `tsv` (full-text).
3. **Embedding:** انتخاب مدل پس از آزمون کیفیت فارسی/انگلیسی (مقایسه‌ی ۲–۳ مدل روی ۲۰ پرسش آزمایشی؛ نتیجه در Decision Log).
4. **Hybrid Search:** جست‌وجوی برداری + متنی (PostgreSQL FTS)؛ ترکیب RRF؛ فیلتر tenant، اعتبار (`approved`)، زبان، حوزه‌ی حقوقی؛ rerank.
5. API داخلی `RetrievalService::retrieve(RetrievalRequest): Collection<Evidence>` با خروجی شامل: chunk، منبع، فصل/صفحه، سال، سطح اعتبار، امتیاز.
6. تشخیص تعارض بین منابع (ساده: دو منبع با ادعای عددی/زمانی متفاوت ⇒ پرچم).
7. UI «جست‌وجوی دانش» + «آزمون بازیابی» (ثبت پرسش‌های آزمایشی و نتایج) و `retrieval_logs`.
8. پردازش دسته‌ای Embedding با Job و کنترل هزینه.

**تست‌ها:** chunking هر نوع منبع، فیلتر tenant، بازیابی پرسش‌های طلایی (golden set)، سنجش recall@k روی نمونه.

**کامیت:** `feat(knowledge): chunking, embeddings and hybrid retrieval`

---

## STEP 17 — Prompt Composer و برنامه‌ریز AI

**اصل‌ها:** بخش ۸ MASTER + بخش‌های ۱۲–۱۵ سند محصول.

**دیتابیس:** `prompt_templates` (`key`, `module`, `version`, `body`, `variables`, `output_schema`, `is_active`)، `ai_plans` (`event_id`, `status`, `input_snapshot` json، `prompt_version`, `model`, `tokens`, `cost`, `created_by`)، `ai_recommendations` (طبق بخش ۱۶ سند محصول: `module`, `title`, `rationale`, `goal_link`, `action_description`, `owner_role`, `suggested_start/due`, `dependencies`, `estimated_cost`, `risk_level`, `kpi`, `evidence_refs` json، `assumptions`, `evidence_status` = `documented|inferred|design_suggestion|needs_review`، `needs_expert_review` bool، `approval_status` = `pending|approved|rejected|deferred`، `revision`)، `ai_call_logs`.

**Prompt Composer:** پرامپت از اجزای مستقل A–H (بخش ۱۳ سند) ساخته می‌شود: نقش، پروفایل رویداد، اهداف، ماژول‌های درخواستی، شواهد بازیابی‌شده، قواعد کشور، قالب خروجی، کنترل کیفیت. قالب هر ماژول جداگانه نسخه‌بندی شود.

**جریان:**
1. فرم: انتخاب ماژول‌ها + مجموعه‌ی منابع.
2. **تشخیص خلأ:** اطلاعات ناقص ← سؤال تکمیلی قبل از تولید.
3. بازیابی شواهد برای هر ماژول جداگانه.
4. فراخوانی مدل با **خروجی JSON Schema** (structured output)؛ اعتبارسنجی؛ در صورت خطا retry محدود.
5. بررسی پس از تولید: هر ادعای «documented» حتماً `evidence_refs` معتبر داشته باشد؛ ارجاع جعلی رد شود.
6. قاعده‌ی ایمنی: ایمنی/پزشکی/حقوقی/مالی ⇒ `needs_expert_review=true`.
7. اتصال به ارائه‌دهنده‌ی مدل از طریق Interface (`LlmClient`) تا بتوان مدل را عوض کرد؛ کلیدها فقط در `.env`؛ **حد سقف هزینه/درخواست ماهانه** از Entitlement.
8. پرامپت‌های نمونه‌ی پایه‌ی بخش ۱۴ سند محصول را به‌عنوان `base` seed کن و حالت‌های هدف (اجتماعی، تجاری، مشارکت، گردشگری، قهرمانی) را به‌عنوان پیکربندی ماژول‌های اولویت‌دار.

**تست‌ها:** با `FakeLlmClient`: اعتبارسنجی schema، رد ارجاع ناموجود، پرچم متخصص، لاگ‌گیری. **تست امنیتی:** فایل حاوی «دستور» (prompt injection) روی خروجی اثر نگذارد.

**کامیت:** `feat(ai): prompt composer and structured event planner`

---

## STEP 18 — پیشنهاد AI ← تأیید ← وظیفه/بودجه/ریسک

**کارها:**
1. صفحه‌ی بازبینی پیشنهادها: گروه‌بندی بر اساس ماژول، نمایش ارجاع (کلیک ← پیش‌نمایش chunk و منبع)، وضعیت شواهد، فرضیات.
2. اقدام‌ها: **تأیید / رد / ویرایش / به‌تعویق**؛ تأیید دسته‌ای.
3. تبدیل پیشنهاد تأییدشده به: `Task` (با `source=ai`)، `Committee` پیشنهادی، `BudgetLine`، `Risk`، `KPI`، `CampaignItem` طبق `module`. وابستگی‌ها و موعدها نگاشت شود.
4. نسخه‌بندی طرح AI و مقایسه‌ی نسخه‌ها.
5. بازخورد کاربر (مفید/نادرست/نامرتبط) + ثبت برای ارزیابی.
6. **هیچ اقدام خودکار بدون تأیید** — حتی «تأیید همه» یک گام تأیید صریح می‌خواهد.
7. دسترسی: فقط مدیر رویداد/مالک سازمان بتواند تأیید کند.

**تست‌ها:** تبدیل صحیح، ممنوعیت اجرای بدون تأیید، ردیابی منبع تا وظیفه، دسترسی.

**کامیت:** `feat(ai): recommendation review and conversion to execution objects`

---

## STEP 19 — ارزیابی پسارویداد و یادگیری سازمانی

**دیتابیس:** `evaluations` (`event_id`, `level` 0–5)، `kpi_definitions`, `kpi_observations`, `surveys`, `survey_responses`, `lessons_learned` (`event_id`, `module`, `text`, `tags`, `visible_to_org`).

**کارها:** مقایسه‌ی هدف و نتیجه، KPI، بودجه‌ی واقعی در برابر مصوب، تحقق تعهدات اسپانسر، شکایات/رضایت (فرم ساده‌ی نظرسنجی با لینک عمومی با توکن)، جلسه‌ی جمع‌بندی (ثبت تصمیم‌ها)، **درس‌آموخته‌ها** که می‌توانند به‌عنوان منبع `scope=organization` وارد پایگاه دانش شوند (با تأیید). ROI فقط در صورت وجود داده‌ی معتبر؛ در غیر این صورت پیام «داده کافی نیست».

**کامیت:** `feat(evaluation): post-event evaluation and lessons learned`

---

## STEP 20 — پنل ادمین، امنیت و ممیزی

**کارها:**
1. **Filament** برای ادمین کل سامانه (کاربران، سازمان‌ها، سقف‌ها، منابع دانش، لاگ AI، گزارش خطا). دسترسی جدا (`is_super_admin` یا نقش) و 2FA/محدودیت IP برای ادمین.
2. بازبینی امنیتی: همه‌ی Policyها، نشت tenant (تست خودکار)، rate limit، validation، هدرهای امنیتی (CSP، HSTS)، `APP_DEBUG=false` در production، ذخیره‌ی فایل خصوصی، sanitize خروجی (XSS)، آپلود امن.
3. ممیزی: نمایش `activity_logs` برای مدیر.
4. سیاست نگهداری/حذف داده (حذف حساب، export داده‌ی کاربر).
5. پشتیبان‌گیری خودکار دیتابیس و فایل‌ها (spatie/laravel-backup یا اسکریپت) و **تست بازیابی**.
6. پایش: Sentry یا معادل، health check، لاگ ساختاریافته.
7. چک‌لیست OWASP Top 10 پر شود و در PROGRESS ثبت.

**کامیت:** `feat(admin): filament admin, audit and security hardening`

---

## STEP 21 — PWA، عملکرد، بهینه‌سازی موبایل

**کارها:** `manifest.webmanifest` و Service Worker (کش دارایی‌ها، صفحه‌ی آفلاین ساده)، آیکون‌ها، نصب روی صفحه‌ی اصلی، بهینه‌سازی تصاویر، lazy loading، ایندکس‌های دیتابیس (بررسی با `EXPLAIN` برای کوئری‌های سنگین)، eager loading و رفع N+1 (با `Model::preventLazyLoading()` در local)، کش (config/route/view)، Lighthouse موبایل ≥ ۸۰ در صفحات اصلی. بررسی UX موبایل برای فرم‌های طولانی (stepper، ذخیره‌ی پیش‌نویس).

**کامیت:** `feat(pwa): installable pwa and performance tuning`

---

## STEP 22 — استقرار و پایلوت

**کارها:**
1. **سرور:** انتخاب با توجه به ایران (VPS داخلی/خارجی، دسترس‌پذیری پیامک‌دهنده و دامنه‌ی `.ir`)؛ Nginx + PHP-FPM + PostgreSQL + Redis + Supervisor/queue worker + scheduler (cron). نسخه‌ی Docker اختیاری.
2. **SSL**، دامنه، `.env` production، `php artisan optimize`.
3. **CI/CD:** GitHub Actions ← تست ← استقرار (SSH/rsync یا Deployer/Envoyer)؛ migration کنترل‌شده؛ rollback.
4. محیط‌ها: local / staging / production.
5. مستندات: راهنمای کاربر کوتاه (کاربر عادی، سازمانی)، راهنمای ادمین.
6. **پایلوت:** ۱–۲ سازمان همکار، یک رویداد واقعی محدود؛ پر کردن منابع واقعی؛ مقایسه‌ی برنامه‌ی AI با برنامه‌ی کارشناسان؛ ثبت زمان، خطا، رضایت؛ تعیین اهداف عددی از قبل (مثلاً کاهش زمان تدوین برنامه، درصد وظایف دارای مسئول و موعد).
7. گزارش پایلوت و اصلاحات.

**کامیت:** `chore(release): production deployment and pilot readiness`

---

## پیوست — پرامپت‌های کمکی (هر زمان لازم شد)

**الف) رفع خطا**
```
این خطا را دقیقاً رفع کن. فقط همین مشکل؛ مرحله را عوض نکن.
نسخه‌ی PHP/Laravel: ...
فایل‌های مرتبط: ...
دستور و خروجی کامل خطا: ...
خروجی: علت، فایل کامل اصلاح‌شده، دستور تست.
```

**ب) بازبینی کد قبل از merge**
```
کد این مرحله را بازبینی کن: امنیت، Policy، نشت tenant، محدودیت‌های حساب، N+1، تست‌های ناقص، ناسازگاری با MASTER. فقط مشکل‌ها را با شدت (بحرانی/مهم/جزئی) و راه‌حل بده.
```

**پ) بازسازی PROGRESS در چت جدید**
```
از روی ساختار مخزن (خروجی tree و git log --oneline -30) و فایل‌های زیر، Manifest و وضعیت مراحل را بازسازی کن و بگو کدام مراحل واقعاً DONE هستند.
```

**ت) تغییر در محصول**
```
می‌خواهم X را تغییر دهم. اثر آن را بر مراحل انجام‌شده و آینده، مدل داده و تست‌ها بسنج، سپس فقط تغییرات لازم را پیشنهاد بده و بخش Decision Log را به‌روز کن.
```
