# 📋 پروژه Sport Events Platform — راهنمای جامع

> **برای چت‌بات‌های دیگر:** این فایل مرجع کامل پروژه است. از اینجا برای درک معماری، وضعیت فعلی، و مراحل بعدی شروع کنید.

**نسخه:** 2.0 | **آخرین به‌روزرسانی:** 1405/08/29 | **وضعیت:** Step 06 ✅

---

## 📑 فهرست

1. [معرفی پروژه](#معرفی-پروژه)
2. [معماری کلی](#معماری-کلی)
3. [نقشه داده‌ای](#نقشه-دادهای)
4. [مراحل انجام‌شده](#مراحل-انجام‌شده-steps-00-06)
5. [مراحل باقی‌مانده](#مراحل-باقی‌مانده-steps-07-22)
6. [راهنمای فنی](#راهنمای-فنی)
7. [شروع کار](#شروع-کار)
8. [امنیت و Policy](#امنیت-و-policy)
9. [منابع](#منابع)

---

## معرفی پروژه

### اطلاعات پایه

| جنبه | توضیح |
|------|-------|
| **نام** | پلتفرم هوشمند برنامه‌ریزی و مدیریت رویدادهای ورزشی |
| **بازار** | ایران (فارسی/RTL) |
| **هدف** | ترکیب مدیریت عملیاتی رویدادهای ورزشی با هوش مصنوعی مبتنی بر پایگاه دانش |
| **وضعیت** | Step 06 (اعضا و نقش‌های رویداد) ✅ |
| **مخزن** | https://github.com/rahneshin/major-events |
| **شاخه اصلی** | `main` (فعلاً Step 00) |
| **شاخه فعلی** | `step/06-event-members` |

### مسئله‌ای که حل می‌کند

برنامه‌ریزی رویدادهای ورزشی میان فایل‌های Word/Excel، تجربه افراد و پیام‌رسان‌ها پخش است:
- هدف رویداد به برنامه اجرایی تبدیل نمی‌شود
- هر دوره از صفر شروع می‌شود
- مسئولیت و موعد شفاف نیست
- دانش در ذهن افراد باقی می‌ماند
- منابع علمی و دستورالعمل‌های سازمانی استفاده نمی‌شوند

### راه‌حل: دو ستون یکپارچه

```
┌─────────────────────────────────────────────┐
│   Event Management Workspace                │
│   (مدیریت عملیاتی رویداد)                  │
│                                             │
│  - سازمان، کاربران، نقش‌ها                │
│  - رویداد، کمیته، وظایف                   │
│  - ثبت‌نام، مسابقات، نتایج                │
│  - بودجه، ریسک، اسپانسرینگ                │
│  - داشبورد، اعلان، خروجی                 │
│  - ارزیابی و درس‌آموخته                  │
└─────────────────────────────────────────────┘
              ↕️ (تعامل)
┌─────────────────────────────────────────────┐
│   Event Planning Intelligence               │
│   (برنامه‌ریز هوشمند)                     │
│                                             │
│  - پایگاه دانش: منابع، چک‌لیست، آیین‌نامه│
│  - بازیابی: جست‌وجوی ترکیبی               │
│  - Prompt Composer: پرامپت پویا            │
│  - تولید برنامه: پیشنهادهای مستند        │
│  - تأیید انسان: وظیفه، بودجه، ریسک       │
└─────────────────────────────────────────────┘
```

### اصل طلایی

> **AI فقط پیشنهاد می‌دهد با ارجاع به منبع؛ هیچ اقدام خودکار بدون تأیید انسان.**

---

## معماری کلی

### سلسله‌مراتب اصلی

```
User ──< Organization ──< Event ──< Committee ──< Task
                              │
                              ├─ EventGoal, EventStakeholder
                              ├─ Budget, Risk, Sponsor
                              └─ Competition ──< Competitor ──< Match
                                          │
                                          └─ Stage ──< Heat/Result

Organization|Global ──< KnowledgeSource ──< KnowledgePage ──< KnowledgeChunk
                                   │
                                   └─ AiRecommendation ──(تأیید)──> Task|Budget|Risk
```

### سطح‌های دسترسی (۴ لایه)

#### 1️⃣ سازمان
```
organization_user.role ∈ {owner|admin|manager|member|viewer}
```

#### 2️⃣ رویداد
```
event_user.role ∈ {event_owner|event_manager|committee_head|member|viewer}
```

#### 3️⃣ کمیته / ورزش
```
committee_user.role ∈ {head|member}
competition_user.role ∈ {manager|scorer}
```

#### 4️⃣ سیستم
```
users.is_super_admin = true  (ادمین کل سامانه)
```

---

## نقشه دادهای

### جداول کلیدی (تا Step 06)

| جدول | وضعیت | نکته |
|------|-------|------|
| `users` | ✅ | uuid، phone (unique، +989xxxxxxxxx)، account_type، status |
| `otp_codes` | ✅ | rate-limited، هش‌شده، انقضا ۲ دقیقه |
| `account_upgrades` | ✅ | لاگ ارتقا از عادی به سازمانی |
| `organizations` | ✅ | uuid، slug (unique)، type، owner_id |
| `organization_user` | ✅ | نقش: owner\|admin\|manager\|member\|viewer |
| `organization_invitations` | ✅ | دعوت با موبایل، توکن (hash) |
| `events` | ✅ | uuid، slug (unique per org)، status، softDeletes |
| `event_user` | ✅ | نقش: event_owner\|event_manager\|... |
| `event_invitations` | ✅ | دعوت رویداد |
| `event_goals` | ✅ | هدف اصلی + فرعی، SMART |
| `event_stakeholders` | ✅ | ذی‌نفعان رویداد |
| `activity_logs` | ✅ | لاگ ممیزی، morphs، action، properties |

### قراردادهای مشترک

```
- کلید: `id` (bigint داخلی)
- UUID: جدول‌های قابل‌نمایش در URL (route binding)
- حذف: `softDeletes` برای users, organizations, events, committees, tasks, competitions
- پول: `unsignedBigInteger` به ریال (نه float)
- JSON: نوع `json` (نه jsonb) برای SQLite + PostgreSQL
- وضعیت: backed enum + cast
- زمان: میلادی/UTC در DB، نمایش شمسی
- Tenant: هر جدول محدود به user/organization/event
- FK: `constrained()` با `cascadeOnDelete` یا `nullOnDelete`
```

### Enum‌ها

```php
AccountType::INDIVIDUAL | ORGANIZATIONAL
OrgRole::OWNER | ADMIN | MANAGER | MEMBER | VIEWER
EventRole::EVENT_OWNER | EVENT_MANAGER | COMMITTEE_HEAD | MEMBER | VIEWER
EventStatus::DRAFT | PLANNING | READY | RUNNING | COMPLETED | ARCHIVED | CANCELLED
EventType::MEGA | MULTI_SPORT | REGIONAL | LOCAL | YOUTH | PARA | ...
EventScale::SMALL | MEDIUM | LARGE
GoalKind::MAIN | SECONDARY
MainGoalType::SOCIAL | COMMERCIAL | PARTICIPATION | TOURISM | PERFORMANCE | MIXED
```

---

## مراحل انجام‌شده (Steps 00-06)

### Step 00 — نصب Laravel و محیط توسعه ✅

**هدف:** اسکلت پروژه، ابزارهای توسعه، CI/CD

**تحویل‌دادنی:**
- Laravel 13.17، PHP 8.3
- Pest 5 (تست)، Pint (سبک)، Larastan (تحلیل سطح 5)
- GitHub Actions CI
- پوشه‌های docs/ai و docs/spec

**مشخصات:**
```
database: SQLite (تا Step 16)
frontend: Livewire 4.4 + Alpine + Tailwind 4
locale: fa (فارسی)
timezone: Asia/Tehran
vcs: Git (GitHub)
```

**Migration‌های اولیه:**
- `2026_11_19_000001_create_otp_codes_table`
- `2026_11_19_000002_create_account_upgrades_table`

---

### Step 01 — زیرساخت UI، RTL، تاریخ شمسی ✅

**هدف:** رابط کاربری موبایل‌اول فارسی

**تحویل‌دادنی:**

| موضوع | توضیح |
|-------|-------|
| **لایوت‌ها** | guest, app, admin, public |
| **کامپوننت‌های Blade** | button, input, select, modal, card, badge, pagination, confirm-dialog, page-header |
| **Jalali Helper** | `Jalali::parse()`, `Jalali::format()` |
| **PhoneNumber Support** | نرمال‌سازی `09xxxxxxxxx` ↔ `+989xxxxxxxxx` و ارقام فارسی/عربی |
| **فونت** | Vazirmatn (self-hosted، 4 وزن: Regular, Medium, SemiBold, Bold) |
| **CSS** | Tailwind 4 + RTL (logical classes: ms-, me-, ps-, pe-) |
| **Motion** | glass, float, rise effects |
| **Styleguide** | صفحه `/styleguide` در local |

**کامپوننت‌ها:**
- `components.button`
- `components.input`
- `components.select`
- `components.textarea`
- `components.card`
- `components.badge`
- `components.modal`
- `components.toast`
- `components.empty-state`
- `components.pagination`
- `components.confirm-dialog`

**Helper‌ها:**
```php
// Jalali
Jalali::toFarsi('2026-11-20')  // ۱۴۰۵/۰۸/۲۹
Jalali::toGregorian('۱۴۰۵/۰۸/۲۹')  // 2026-11-20

// PhoneNumber
PhoneNumber::normalize('09123456789')  // +989123456789
PhoneNumber::format('+989123456789')  // 0912-345-6789
PhoneNumber::validate('09123456789')  // true|false
```

---

### Step 02 — ورود با OTP (بدون رمز) ✅

**هدف:** احراز هویت موبایل محور

#### جریان

```
صفحه /login
  ↓ شماره موبایل
صفحه /verify
  ↓ OTP (۶ رقم)
تأیید صحیح
  ↓ کاربر جدید؟
صفحه /complete-profile (نام)
  ↓
صفحه /select-account-type
  ↓
Redirect /app
```

#### Livewire کامپوننت‌ها

- `Auth\PhoneLogin` — شماره موبایل
- `Auth\VerifyOtp` — کد + تایمر ارسال مجدد
- `Auth\CompleteProfile` — نام کاربر

#### امنیت

```
- کد: Hash::make() (هش‌شده در DB)
- انقضا: ۲ دقیقه
- حداکثر تلاش: ۵ بار
- Rate-limit: ۱ کد/۶۰ث، ۵ کد/ساعت (شماره)
  - ۲۰ درخواست/ساعت (IP)
- Throttle route: 60/1 (۶۰ درخواست به ۱ دقیقه)
- پیام یکسان: «کد ارسال شد» (افشای وجود کاربر نشود)
```

#### SmsGateway

```php
interface SmsGateway {
    send(string $phone, string $message): bool;
}

// Drivers:
// - LogSmsGateway: storage/logs (توسعه)
// - NullSmsGateway: nothing (تست)
// - [سرویس واقعی]: تا انتخاب
```

#### Middleware

```php
middleware('guest')  // /login, /verify
middleware('auth')   // /complete-profile, /select-account-type
```

**Migration:**
```
2026_11_19_000001_create_otp_codes_table
```

---

### Step 03 — نوع حساب و Entitlement ✅

**هدف:** کنترل متمرکز سقف‌ها

#### دو نوع حساب

| ویژگی | عادی | سازمانی |
|------|------|---------|
| رویداد فعال | 1 | نامحدود |
| کمیته/رویداد | 3 | نامحدود |
| عضو/رویداد | 10 | نامحدود |
| وظیفه/رویداد | 50 | نامحدود |
| سازمان | 0 | 3 |
| AI req/month | 0 | 100 |
| مسابقات | ❌ | ✅ |
| features | {tasks_basic, budget_basic, export_pdf} | {*} |

**منبع حقیقت:** `config/plans.php`

```php
'individual' => [
    'limits' => ['active_events' => 1, ...],
    'features' => ['tasks_basic', 'budget_basic', 'export_pdf'],
],
'organizational' => [
    'limits' => ['active_events' => null, ...],
    'features' => ['*'],
],
```

#### EntitlementService

```php
// API
EntitlementService::can($user, 'feature'): bool
EntitlementService::limit($user, 'key'): ?int
EntitlementService::remaining($user, 'key'): int
EntitlementService::assertWithinLimit($user, 'key'): void

// نمونه
if (EntitlementService::can($user, 'export_word')) {
    // دسترسی فقط سازمانی
}

$remaining = EntitlementService::remaining($user, 'active_events');
if ($remaining <= 0) throw new LimitExceededException();
```

#### Onboarding

1. **صفحه SelectAccountType:** دو کارت (عادی + سازمانی)
2. **ذخیره:** `users.account_type`
3. **Middleware:** `EnsureAccountTypeSelected`
4. **صفحه Account:** مشاهده و ارتقا

#### Blade Directive

```php
@feature('export_word')
    <!-- فقط سازمانی دیده می‌شود -->
@endfeature

@unless(feature('myCan'))
    <!-- فقط اگر عادی -->
@endunless
```

#### قاعده ارتقا

```
عادی → سازمانی: مجاز ✅
سازمانی → عادی: ممنوع ❌ (جلوگیری از نقض سقف)
```

**Migration:**
```
2026_11_19_000002_create_account_upgrades_table
```

---

### Step 04 — سازمان مجازی و اعضا ✅

**هدف:** Tenant سازمانی

#### موجودیت‌های اصلی

```php
Organization {
    id, uuid, name, slug (unique),
    type (federation|club|academy|university|...),
    description?, city?, logo_path?,
    owner_id, status (active|suspended),
    timestamps, softDeletes
}

OrganizationUser {
    organization_id, user_id,
    role (owner|admin|manager|member|viewer),
    joined_at, timestamps,
    unique(organization_id, user_id)
}

OrganizationInvitation {
    organization_id, phone, role,
    token_hash, invited_by, expires_at,
    accepted_at?, status (pending|accepted|expired|cancelled),
    timestamps, index(organization_id, phone)
}
```

#### منطق

```
✅ فقط account_type=organizational می‌تواند سازمان بسازد
✅ سازنده = owner خودکار
✅ دعوت با شماره:
   - کاربر موجود؟ → اعلان درون‌برنامه + پیامک
   - کاربر جدید؟ → پیامک لینک قبول + ورود + membership
✅ Context Switcher: users.current_organization_id در session
✅ انتقال مالکیت: مالک با transfer می‌تواند ترک کند
✅ Entitlement: سقف organizations (عادی: 0، سازمانی: 3)
```

#### Policy: OrganizationPolicy

```php
view(User $user, Organization $org)  // عضو | مالک سازمان
update()          // owner | admin
delete()          // owner فقط
manageMembers()   // owner | admin
invite()          // owner | admin
transferOwnership() // owner فقط
```

#### صفحات Livewire

- `Orgs\OrganizationIndex` — لیست سازمان‌ها
- `Orgs\OrganizationForm` — ایجاد/ویرایش
- `Orgs\OrganizationShow` — جزئیات + اعضا + دعوت‌ها

**Migration:**
```
2026_11_19_000003_create_organizations_tables
```

---

### Step 05 — رویداد (هسته) ✅

**هدف:** ایجاد و مدیریت رویداد

#### موجودیت‌های اصلی

```php
Event {
    id, uuid, organization_id (nullable),
    created_by, title, slug (unique per org),
    sport_type?, event_type (enum),
    scale (small|medium|large),
    status (enum: draft→planning→ready→running→completed→archived),
    start_date?, end_date?,
    venue_name?, city?, province?,
    expected_participants?, expected_spectators?,
    budget_estimate? (ریال), main_goal_type?,
    description?, metadata? (json),
    timestamps, softDeletes,
    unique(organization_id, slug)
}

EventGoal {
    event_id, kind (main|secondary),
    title, description?, priority (tinyint),
    smart_measure?, target_value?, unit?,
    timestamps, index(event_id, kind)
}

EventStakeholder {
    event_id, name, role?, notes?,
    timestamps
}
```

#### گذار وضعیت

```
draft ──→ planning ──→ ready ──→ running ──→ completed ──→ archived
  ↓                                                        ↑
  └─────────────── cancelled ←──────────────────────────┘

✅ مجاز
❌ draft → completed مستقیم (ممنوع)
✅ cancelled از هر وضعیت
```

**رویداد فعال:** `draft..running` (برای سقف Entitlement)

#### Entitlement

```
سقف active_events:
  - عادی: 1
  - سازمانی: نامحدود
```

#### صفحه‌ی Wizard (فرم ایجاد)

```
مرحله 1: اطلاعات پایه (عنوان، نوع)
        ↓
مرحله 2: اهداف و اولویت
        ↓
مرحله 3: مکان و زمان‌بندی
        ↓
مرحله 4: خلاصه (ذخیره)
```

#### صفحه‌ی Hub (نمایش رویداد)

```
سرصفحه: نام رویداد + وضعیت + عملیات
        ↓
تب‌ها: کمیته | وظایف | بودجه | ریسک | اعضا | مسابقات
        ↓
محتوای هر تب: Placeholder با @feature() یا کامپوننت Livewire
```

#### Policy: EventPolicy

```php
// Gate::before: مالک سازمان دسترسی کامل
view()      // event_user موجود
update()    // event_owner | event_manager | مالک سازمان
delete()    // event_owner | مالک سازمان
manageMembers() // event_manager+
```

**Migration:**
```
2026_11_20_000004_create_events_tables
```

---

### Step 06 — اعضا و نقش‌های رویداد، Activity Log ✅

**هدف:** همکاری و ممیزی

#### موجودیت‌های اصلی

```php
EventUser {
    event_id, user_id,
    role (event_owner|event_manager|committee_head|member|viewer),
    added_by, joined_at,
    timestamps, unique(event_id, user_id)
}

EventInvitation {
    event_id, phone, role,
    token_hash, invited_by, expires_at,
    accepted_at?, status (pending|accepted|expired|cancelled),
    timestamps, index(phone, status)
}

ActivityLog {
    user_id?, event_id?,
    subject_type, subject_id (morphs),
    action (created|updated|deleted),
    properties? (json), ip?,
    timestamps, index(event_id, created_at)
}
```

#### منطق

```
✅ دعوت با موبایل (Service مشترک با Step 04)
✅ سقف members_per_event از Entitlement
✅ سازنده رویداد = event_owner خودکار
✅ Activity log: Trait LogsActivity برای تغییرات مهم
   - Event created|updated
   - EventUser added|updated|deleted
   - Task status changed
   - تمام تغییرات مسابقات
```

#### Policy: EventPolicy (کامل)

```php
view()         // عضو | مالک سازمان
update()       // event_owner | event_manager | مالک سازمان
delete()       // event_owner | مالک سازمان
manageMembers() // event_manager | مالک سازمان
invite()       // event_manager | مالک سازمان
```

#### Scope Query

```php
Event::visibleTo($user)  // رویدادهایی که کاربر می‌تواند ببیند
```

#### صفحه‌ی اعضا (تب در Hub)

```
لیست اعضا:
  - نام | نقش | موعد عضویت | عملیات (حذف / تغییر نقش)

فرم دعوت:
  - انتخاب شماره یا از لیست سازمان
  - انتخاب نقش
  - ارسال دعوت

لیست دعوت‌های در انتظار:
  - نام/شماره | نقش | موعد انقضا | عملیات (لغو)
```

#### Trait: LogsActivity

```php
class Event extends Model {
    use LogsActivity;
    
    protected $recordEvents = ['created', 'updated', 'deleted'];
}

// خودکار ثبت می‌شود:
// - چه کاربری
// - چه تغییری
// - قبل و بعد مقادیر
```

**Migration:**
```
2026_11_20_000005_create_event_members_tables
```

---

## مراحل باقی‌مانده (Steps 07-22)

### Step 07 — کمیته‌ها (⬜)

```
Committee {
    event_id, name,
    type (executive|finance|technical_competitions|medical_safety|
          logistics|hr_volunteers|security|protocol|custom),
    description?, head_user_id?, color, position
}

CommitteeUser {
    committee_id, user_id,
    role (head|member), timestamps, unique(committee_id, user_id)
}
```

**ویژگی‌ها:**
- سقف: `committees_per_event` (عادی: 3)
- قالب پیشنهادی: بر اساس `event_type`
- عضو کمیته = عضو رویداد باید باشد
- تب کمیته‌ها در Hub

---

### Step 08 — وظایف، وابستگی، Milestone

```
Task {
    event_id, committee_id (nullable), parent_id (nullable),
    title, description, phase (pre|during|post),
    module (strategy|operations|budget|marketing|sponsorship|risk|
            logistics|competitions|evaluation),
    assignee_id, status (todo|doing|blocked|done|cancelled),
    priority, start_at, due_at, completed_at,
    estimated_hours, source (manual|ai),
    ai_recommendation_id (nullable),
    position, timestamps, softDeletes
}

TaskDependency {
    task_id, depends_on_task_id,
    type (finish_to_start)
}

Milestone {
    event_id, title, due_at, status,
    timestamps
}
```

**نماها:** لیست، Kanban (Sortable)، تقویم، وظایف من  
**دستور:** `tasks:remind` برای موعدهای نزدیک

---

### Step 09 — بودجه و درآمد

```
BudgetLine {
    event_id, type (expense|revenue),
    category, name,
    cost_behavior (fixed|variable),
    planned_amount (ریال),
    committee_id (nullable),
    assumption?, version
}

Transaction {
    budget_line_id, amount, date,
    description, receipt_path?, created_by
}

BudgetVersion {
    snapshot مصوب
}
```

**ویژگی‌ها:**
- نسخه‌ها: مقدماتی ← مصوب (قفل)
- مقایسه: برنامه vs واقعی
- هشدار: > 10% اضافه‌هزینه

---

### Step 10 — ریسک

```
Risk {
    event_id, title, category,
    description, likelihood (1-5), impact (1-5),
    score (محاسبه‌شده), response (accept|reduce|avoid|transfer),
    control_actions, owner_id, status, review_date
}

Incident {
    event_id, risk_id (nullable),
    title, severity, reported_by,
    occurred_at, resolution
}
```

**ویژگی:** ماتریس ۵×۵ تعاملی

---

### Step 11 — اسپانسرینگ و بازاریابی

```
Sponsor {
    event_id, name, category,
    contact_name, contact_phone,
    status (prospect|negotiating|signed|delivered|lost),
    cash_value, in_kind_value, notes
}

SponsorshipAsset { ... }
CampaignItem { ... }
MarketingPlan { ... }
```

**فقط برای:** `account_type=organizational`

---

### Step 12 — مسابقات 🎯 (۶ بخش)

**اصل:** قرعه، جدول، رده‌بندی با **الگوریتم قطعی و تست‌شده**

#### 12A — ثبت‌نام

```
Competition {
    event_id, name, gender (men|women|mixed|open),
    participant_type (team|individual),
    result_type (match|ranked),
    scoring_config (json), status,
    requires_confirmation, capacity, registration_*
}

Competitor {
    competition_id, name, affiliation,
    seed?, final_rank?, status
}

CompetitorMember {
    competitor_id, user_id?,
    full_name, role?
}
```

#### 12B — قرعه و جدول

```
Stage {
    competition_id, format (round_robin|knockout|groups|heats),
    config (json), is_final, status
}

Draw {
    stage_id, method, rng_seed, result
}

Match {
    stage_id, a_id, b_id, round, leg,
    score_a, score_b, points_a, points_b,
    winner_id, result_status (entered|confirmed)
}
```

**الگوریتم:**
- لیگ: روش دایره‌ای (۱۵ تیم = ۳۰ دور = ۲۱۰ بازی)
- حذفی: سیدبندی + بای
- قرعه: بازتولید‌پذیر با seed

#### 12C — ثبت نتیجه

#### 12D — تک‌حذفی

#### 12E — ورزش‌های رتبه‌ای

#### 12F — دوحذفی

---

### Step 13-22

**خلاصه:**
- **13:** داشبورد و اعلان
- **14:** خروجی (PDF/Word/Excel)
- **15:** پایگاه دانش: آپلود
- **16:** Embedding و جست‌وجو
- **17:** Prompt Composer
- **18:** پیشنهاد AI ← تأیید
- **19:** ارزیابی پسارویداد
- **20:** پنل ادمین (Filament)
- **21:** PWA و بهینه‌سازی
- **22:** استقرار و پایلوت

---

## راهنمای فنی

### Stack و نسخه‌ها

```
PHP 8.3
Laravel 13.17
Livewire 4.4
Tailwind 4
PostgreSQL (Step 16+) / SQLite (تا Step 16)
Pest 5 + pest-plugin-laravel
Pint (preset: laravel + ordered_imports alpha)
Larastan (level 5)
GitHub Actions CI
Node 20+
```

### ساختار پوشه‌ها

```
app/
├── Models/
│   ├── User.php
│   ├── Organization.php
│   ├── Event.php
│   ├── EventUser.php
│   ├── ActivityLog.php
│   └── ...
├── Enums/
│   ├── AccountType.php
│   ├── OrgRole.php
│   ├── EventRole.php
│   ├── EventStatus.php
│   └── ...
├── Policies/
│   ├── OrganizationPolicy.php
│   ├── EventPolicy.php
│   └── ...
├── Services/
│   ├── OtpService.php
│   ├── EntitlementService.php
│   ├── OrganizationService.php
│   ├── EventService.php
│   ├── EventMemberService.php
│   ├── Competitions/
│   │   └── Engine/ (Step 12)
│   └── ...
├── Livewire/
│   ├── Auth/
│   │   ├── PhoneLogin.php
│   │   ├── VerifyOtp.php
│   │   ├── CompleteProfile.php
│   │   └── SelectAccountType.php
│   ├── Orgs/
│   │   ├── OrganizationIndex.php
│   │   ├── OrganizationForm.php
│   │   └── OrganizationShow.php
│   ├── Events/
│   │   ├── EventIndex.php
│   │   ├── EventForm.php
│   │   └── EventShow.php
│   ├── App/
│   │   └── Dashboard.php
│   ├── Admin/
│   │   └── Dashboard.php
│   └── Account/
│       └── AccountPage.php
├── Enums/
├── Http/
│   ├── Middleware/
│   ├── Requests/
│   └── ...
├── Support/
│   ├── Jalali.php
│   ├── PhoneNumber.php
│   └── LogsActivity.php
└── ...

config/
├── plans.php
└── ...

database/
├── migrations/
│   ├── 2026_11_19_000001_create_otp_codes_table.php
│   ├── 2026_11_19_000002_create_account_upgrades_table.php
│   ├── 2026_11_19_000003_create_organizations_tables.php
│   ├── 2026_11_20_000004_create_events_tables.php
│   └── 2026_11_20_000005_create_event_members_tables.php
└── seeders/

lang/fa/
├── ui.php
├── auth.php
├── account.php
├── organization.php
├── event.php
└── ...

resources/
├── views/
│   ├── layouts/
│   │   ├── guest.blade.php
│   │   ├── app.blade.php
│   │   ├── admin.blade.php
│   │   └── public.blade.php
│   ├── components/
│   │   ├── button.blade.php
│   │   ├── input.blade.php
│   │   └── ...
│   ├── livewire/
│   │   ├── auth/
│   │   ├── orgs/
│   │   ├── events/
│   │   └── ...
│   └── public/
│       └── home.blade.php
└── css/
    └── app.css

docs/
├── ai/
│   ├── 00-MASTER-CONTEXT.md
│   ├── 01-DOMAIN-MODEL.md
│   ├── 02-COMPETITIONS.md
│   ├── 03-KNOWLEDGE-AND-AI.md
│   ├── 10-STEPS-PART1.md
│   ├── 11-STEPS-PART2.md
│   ├── 90-PROGRESS.md
│   └── PROJECT-OVERVIEW.md (این فایل)
└── spec/
    └── product-roadmap.md

routes/
└── web.php

tests/
├── Feature/
│   ├── Account/
│   ├── Auth/
│   ├── Orgs/
│   └── ...
├── Unit/
│   ├── JalaliTest.php
│   └── PhoneNumberTest.php
└── Pest.php
```

### قواعس (Rules)

```
1. کنترلر نازک: منطق در Service
2. UUID در URL: route binding
3. Soft Delete: users, organizations, events, committees, tasks, competitions
4. Entitlement متمرکز: EntitlementService فقط
5. جداسازی Tenant: همه‌ی query‌ها محدود (تست نشت اجباری)
6. Policy برای هر route: تست ۴۰۳ اجباری
7. Activity Log: تغییرات مهم ثبت شود
8. Pint + Larastan: بدون خطا
9. مدل Match → SportMatch: Match رزرو‌شده‌ی PHP 8
10. JSON: نوع json (نه jsonb) برای SQLite + PostgreSQL
```

### فایل‌های کلیدی

```
config/plans.php          سقف‌ها و feature flags
routes/web.php            همه‌ی route‌ها
app/Support/Jalali.php    تبدیل تاریخ
app/Support/PhoneNumber.php نرمال‌سازی موبایل
app/Services/EntitlementService.php کنترل سقف
app/Services/EventService.php منطق رویداد
lang/fa/                  تمام متن‌های UI
```

---

## شروع کار

### راه‌اندازی محیط

```bash
# Clone
git clone https://github.com/rahneshin/major-events
cd sport-events

# نصب
composer install
npm install
npm run build

# تنظیم .env
cp .env.example .env
php artisan key:generate

# دیتابیس
php artisan migrate
php artisan db:seed

# تست
php artisan test

# توسعه
php artisan serve
# npm run dev  (در terminal جدا)
```

### اولین استفاده

```
1. صفحه اصلی: http://localhost:8000
2. ورود: شماره موبایل
3. OTP: storage/logs/laravel.log
4. انتخاب نوع: عادی یا سازمانی
5. داشبورد: http://localhost:8000/app
```

### دستورهای مفید

```bash
# تست
php artisan test
php artisan test tests/Feature/Auth/OtpLoginTest.php

# Linting
./vendor/bin/pint
./vendor/bin/pint --test  # check only

# Larastan
./vendor/bin/larastan analyse --level=5

# Migrate
php artisan migrate
php artisan migrate:refresh
php artisan migrate:fresh --seed

# Tinker
php artisan tinker
> User::first()

# Scheduler (روزانه)
# در crontab: * * * * * cd /project && php artisan schedule:run

# Queue (اگر صف استفاده شود)
php artisan queue:work
```

---

## امنیت و Policy

### قواعس دسترسی

#### سطح سازمان

```
owner      → تمام رویدادهای سازمان + مدیریت اعضا
admin      → رویدادهای سازمان + مدیریت محدود
manager    → رویدادهای سازمان
member     → رویدادهای سازمان (مشاهده)
viewer     → مشاهده فقط
```

#### سطح رویداد

```
event_owner    → تمام عملیات
event_manager  → ویرایش + مدیریت اعضا
committee_head → وظایف کمیته + بودجه
member         → مشاهده + نوشتن comment
viewer         → مشاهده فقط
```

#### سطح سیستم

```
is_super_admin=true → کل سامانه
```

### تست‌های اجباری

```
✅ ۴۰۳ برای هر route غیرعمومی
✅ جداسازی tenant (کاربر B نتواند رویداد A را ببیند)
✅ سقف Entitlement (تجاوز = Exception)
✅ Activity log ثبت شود
✅ Policy enforcement
```

### حفاظت

```
✅ CSRF: Laravel built-in
✅ SQL Injection: Eloquent + parameterized queries
✅ XSS: Blade auto-escaping
✅ Mass Assignment: fillable/guarded
✅ File Upload: validation + secure path
✅ Encryption: Laravel's built-in
✅ Phone Privacy: هش نمی‌شود، فقط normalize
```

---

## منابع

### فایل‌های اصلی

```
docs/ai/00-MASTER-CONTEXT.md        اصول و قانون پروژه
docs/ai/01-DOMAIN-MODEL.md          نقشه داده‌ای کامل
docs/ai/02-COMPETITIONS.md          الگوریتم‌های مسابقات
docs/ai/03-KNOWLEDGE-AND-AI.md      پایگاه دانش و RAG
docs/ai/10-STEPS-PART1.md           مراحل 00-10
docs/ai/11-STEPS-PART2.md           مراحل 11-22
docs/ai/90-PROGRESS.md              وضعیت فعلی + Changelog
docs/spec/product-roadmap.md        نقشه راه محصول
```

### Git

```
مخزن: https://github.com/rahneshin/major-events
شاخه اصلی: main (Step 00 فعلاً)
شاخه فعلی: step/06-event-members
```

### داکومنتیشن

```
Laravel: https://laravel.com/docs/13.x
Livewire: https://livewire.laravel.com/docs
Pest: https://pestphp.com
Tailwind: https://tailwindcss.com
```

---

## یادداشت‌های مهم

### برای چت‌بات‌های بعدی

1. **وضعیت فعلی:** Step 06 ✅
2. **شاخه کاری:** `step/06-event-members`
3. **ریدمی پروژه:** `docs/ai/90-PROGRESS.md` را بخوانید
4. **برای شروع مرحله جدید:**
   - فایل `docs/ai/10-STEPS-PART1.md` یا `11-STEPS-PART2.md` را بخوانید
   - اطلاعات از `docs/ai/00-MASTER-CONTEXT.md` بگیرید
   - دستوری: «مرحله N را اجرا کن»

### مشکلات شناخته‌شده

```
1. فونت Vazirmatn: ممکن است woff2 کامل نباشد
2. SMS: driver=log (انتخاب سرویس واقعی موکول)
3. UI دعوت رویداد: از لینک عمومی کامل نیست
4. main branch: عقب است (Step 06 در step/06-event-members)
5. تست Event/Members: کامل نیست
6. بعد از pull: npm run build + php artisan migrate اجباری
```

---

**آخرین به‌روزرسانی:** ۱۴۰۵/۰۸/۲۹  
**آماده برای:** تمام چت‌بات‌های مستقبل  
**نسخه:** 2.0
