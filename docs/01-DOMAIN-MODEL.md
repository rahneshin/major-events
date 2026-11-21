# 01 — DOMAIN MODEL (نقشه‌ی کامل داده‌ی سامانه)

> **هدف این فایل:** هر AI بتواند بفهمد کل سامانه از چه موجودیت‌هایی ساخته می‌شود، کدام‌ها **همین الان در کد وجود دارند** و کدام‌ها **برنامه‌ریزی‌شده‌اند**، و چطور به هم وصل‌اند. جزئیات ستون‌های هر مرحله در فایل مرحله است؛ مسابقات در `02-COMPETITIONS.md`؛ دانش و AI در `03-KNOWLEDGE-AND-AI.md`.
>
> **علامت‌ها:** ✅ = در migration واقعی مخزن (شاخه‌ی `step/06-event-members`) تأیید شده · ⬜ = برنامه‌ریزی‌شده (طبق فایل مرحله؛ قبل از ساخت، با کد واقعی تطبیق بده).

---

## ۱. قراردادهای مشترک همه‌ی جدول‌ها

| قرارداد | قاعده |
|---|---|
| کلید | `id` bigint داخلی؛ جدول‌های قابل‌نمایش در URL: `uuid` یکتا + route binding با uuid |
| نام | جمع انگلیسی snake_case؛ جدول واسط: `a_b` مفرد (مثل `organization_user`, `event_user`, `committee_user`) |
| حذف | `softDeletes` برای: users, organizations, events, committees, tasks, competitions |
| پول | `unsignedBigInteger` به ریال؛ ستون با نام `*_amount` یا `budget_estimate` |
| JSON | نوع `json` (نه `jsonb`) تا روی SQLite و PostgreSQL یکسان کار کند؛ cast به array در مدل |
| وضعیت و نوع | ستون `string` + backed enum PHP + cast؛ مقادیر مجاز در کامنت migration |
| زمان | میلادی/UTC در DB؛ نمایش شمسی با `App\Support\Jalali` |
| tenant | هر جدول باید با یک مسیر روشن به `user`/`organization`/`event` محدود شود؛ query بدون محدودسازی ممنوع |
| ممیزی | تغییرهای مهم با trait `LogsActivity` در `activity_logs` ثبت می‌شود |
| FK | `constrained()` با `cascadeOnDelete` یا `nullOnDelete` صریح |

---

## ۲. هویت، حساب، سازمان

### ✅ `users`
`id, uuid, name?, phone (unique, +989…), phone_verified_at?, account_type? (individual|organizational), avatar_path?, last_login_at?, status (active|suspended), current_organization_id? (FK organizations, nullOnDelete), remember_token, timestamps, softDeletes`
⬜ **06B:** `is_super_admin boolean default false` (ادمین سیستم).

### ✅ `otp_codes`
`id, phone (index), code_hash, purpose (login), expires_at, attempts (tinyint), consumed_at?, ip?, user_agent?, timestamps`

### ✅ `account_upgrades`
`id, user_id, from_type?, to_type, reason?, performed_by?, timestamps` (لاگ ارتقا)

### ✅ `organizations`
`id, uuid, name, slug (unique), type (federation|club|academy|university|municipality|company|other), description?, city?, logo_path?, owner_id (FK users), status (active|suspended), timestamps, softDeletes`

### ✅ `organization_user`
`id, organization_id, user_id, role (owner|admin|manager|member|viewer), joined_at?, timestamps; unique(organization_id, user_id)`

### ✅ `organization_invitations`
`id, organization_id, phone, role, token_hash, invited_by, expires_at, accepted_at?, status (pending|accepted|expired|cancelled), timestamps; index(organization_id, phone)`

---

## ۳. رویداد و همکاری

### ✅ `events`
`id, uuid, organization_id? (null = رویداد شخصی), created_by, title, slug, sport_type?, event_type (mega|multi_sport|regional|local|youth|para|community|tournament|league|festival|other), scale (small|medium|large), status (draft|planning|ready|running|completed|archived|cancelled), start_date?, end_date?, venue_name?, city?, province?, expected_participants?, expected_spectators?, budget_estimate? (ریال), main_goal_type? (social|commercial|participation|tourism|performance|mixed), description?, metadata? (json), timestamps, softDeletes; unique(organization_id, slug); index(created_by, status)`

**گردش وضعیت:** `draft → planning → ready → running → completed → archived`؛ `cancelled` از هر وضعیتِ غیرپایانی؛ **`draft → completed` مستقیم ممنوع**. «رویداد فعال» = `draft..running` (برای سقف Entitlement).

### ✅ `event_goals`
`id, event_id, kind (main|secondary), title, description?, priority (tinyint), smart_measure?, target_value?, unit?, timestamps; index(event_id, kind)`

### ✅ `event_stakeholders`
`id, event_id, name, role?, notes?, timestamps`

### ✅ `event_user`
`id, event_id, user_id, role (event_owner|event_manager|committee_head|member|viewer), added_by?, joined_at?, timestamps; unique(event_id, user_id)`

### ✅ `event_invitations`
`id, event_id, phone, role, token (64, unique), invited_by, expires_at, accepted_at?, status, timestamps; index(phone, status)`
> ⚠️ **ناهماهنگی شناخته‌شده:** این‌جا `token` خام ذخیره می‌شود ولی در `organization_invitations` هش (`token_hash`) است. در 06B یکسان‌سازی شود (هش + مقایسه‌ی امن).

### ✅ `activity_logs`
`id, user_id?, event_id?, subject (nullableMorphs), action, properties? (json), ip?, timestamps; index(event_id, created_at)`

---

## ۴. کمیته و وظیفه (Step 07–08) ⬜

| جدول | ستون‌های کلیدی |
|---|---|
| `committees` | `id, uuid, event_id, name, type (executive|finance|marketing_media|technical_competitions|medical_safety|logistics|hr_volunteers|security|protocol|custom), description?, head_user_id?, color, position, softDeletes` |
| `committee_user` | `committee_id, user_id, role (head|member)`؛ عضو کمیته باید عضو رویداد باشد |
| `tasks` | `id, uuid, event_id, committee_id?, parent_id?, competition_id? (وظیفه‌ی مربوط به یک ورزش), title, description, phase (pre|during|post), module (strategy|operations|budget|marketing|sponsorship|risk|logistics|competitions|evaluation), assignee_id?, status (todo|doing|blocked|done|cancelled), priority, start_at?, due_at?, completed_at?, estimated_hours?, source (manual|ai), ai_recommendation_id?, position, softDeletes` |
| `task_dependencies` | `task_id, depends_on_task_id, type=finish_to_start` (بدون چرخه؛ DFS) |
| `task_comments`, `task_attachments` | کامنت؛ فایل خصوصی با محدودیت نوع/اندازه |
| `milestones` | `event_id, title, due_at, status` |

---

## ۵. بودجه، ریسک، اسپانسرینگ (Step 09–11) ⬜

| جدول | ستون‌های کلیدی |
|---|---|
| `budget_lines` | `event_id, type (expense|revenue), category, name, cost_behavior (fixed|variable), planned_amount (ریال), committee_id?, assumption?, version` |
| `transactions` | `budget_line_id, amount, date, description, receipt_path?, created_by` |
| `budget_versions` | snapshot مصوب (قفل با تأیید مدیر) |
| `risks` | `event_id, title, category, description, likelihood (1–5), impact (1–5), score (محاسبه‌شده), response (accept|reduce|avoid|transfer), control_actions, owner_id, status, review_date` |
| `incidents` | `event_id, risk_id?, title, severity, reported_by, occurred_at, resolution` |
| `sponsors` | `event_id, name, category, contact_*, status (prospect|negotiating|signed|delivered|lost), cash_value, in_kind_value` |
| `sponsorship_assets`, `sponsor_deliverables` | دارایی قابل‌ارائه؛ تعهد با موعد و مدرک |
| `marketing_plans`, `campaign_items` | بخش‌بندی/پیام/کانال؛ تقویم محتوا |

---

## ۶. مسابقات (Step 12) ⬜ — خلاصه‌ی جدول‌ها (جزئیات کامل: `02-COMPETITIONS.md`)

`competitions` · `competition_user` · `competition_templates` · `competitors` · `competitor_members` · `stages` · `stage_entries` · `matches` (مدل `SportMatch`) · `match_games` · `heats` · `heat_results` · `draws` · `competition_notes`

---

## ۷. اعلان و خروجی (Step 13–14) ⬜

`notifications` (جدول Laravel؛ کانال‌های database و sms) · `notification_preferences` · `generated_exports` (نوع، مسیر خصوصی، signed URL موقت)

---

## ۸. پایگاه دانش و AI (Step 15–18) ⬜ — خلاصه (جزئیات: `03-KNOWLEDGE-AND-AI.md`)

| جدول | نکته |
|---|---|
| `knowledge_sources` | `scope (global|organization)`, شناسنامه (ناشناخته = null), `authority_level (A|B|C|D)`, `review_status`, `license_status`, `file_hash` |
| `knowledge_source_versions`, `knowledge_pages`, `knowledge_topics` | نسخه؛ متن به تفکیک فصل/صفحه/اسلاید + flags؛ ۱۳ موضوع |
| `knowledge_chunks` | embedding + متن کامل + فیلترهای tenant/اعتبار/زبان/حوزه + `sport_tags` |
| `retrieval_logs` | لاگ بازیابی و آزمون |
| `prompt_templates`, `ai_plans`, `ai_recommendations`, `ai_call_logs` | قالب نسخه‌دار؛ طرح؛ پیشنهاد با `evidence_refs`؛ لاگ هزینه |
| `recommendation_citations` | ارجاع پیشنهاد به chunk |

---

## ۹. ارزیابی (Step 19) ⬜

`evaluations (level 0–5)` · `kpi_definitions` · `kpi_observations` · `surveys` · `survey_responses` · `lessons_learned (visible_to_org)` — درس‌آموخته‌ی تأییدشده می‌تواند به‌عنوان منبع `scope=organization` وارد پایگاه دانش شود.

---

## ۱۰. ماتریس دسترسی هدف (خلاصه؛ در هر مرحله با تست تکمیل شود)

| عملیات | مالک/مدیر سازمان | event_owner | event_manager | committee_head | member | viewer | ادمین سیستم |
|---|---|---|---|---|---|---|---|
| دیدن رویداد | ✅ (رویدادهای سازمان) | ✅ | ✅ | ✅ | ✅ | ✅ | فقط آمار/پنل |
| ویرایش رویداد | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| مدیریت اعضای رویداد | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| ساخت/ویرایش کمیته | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| تغییر وضعیت وظیفه | ✅ | ✅ | ✅ | کمیته‌ی خودش | فقط assignee | ❌ | ❌ |
| بودجه (ویرایش) | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| تأیید پیشنهاد AI | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| ثبت نتیجه‌ی مسابقه | ✅ | ✅ | ✅ | — | `scorer` همان ورزش | ❌ | ❌ |
| تأیید نتیجه‌ی مسابقه | ✅ | ✅ | ✅ | — | `manager` همان ورزش | ❌ | ❌ |
| پنل ادمین/سیستم | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |

---

## ۱۱. نمودار وابستگی ماژول‌ها (چه چیزی قبل از چه چیزی)

```
User/OTP → Account/Entitlement → Organization → Event → EventMembers+ActivityLog
                                                   ├─ Committee → Task → (Budget, Risk, Sponsorship)
                                                   ├─ Competition (مسابقات)  ← فقط accountِ سازمانی
                                                   └─ Dashboard/Notification → Export
KnowledgeSource → Page → Chunk → Retrieval → PromptComposer → AiPlan → Recommendation
        └──(تأیید انسان)──> Task | Committee | BudgetLine | Risk | KPI | CampaignItem
Competition(ready) ──> AI «پیشنهاد آمادگی اجرا» ──(تأیید انسان)──> Task(competition_id) | CompetitionNote
```
