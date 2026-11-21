# 02 — COMPETITIONS (ماژول مسابقات: طراحی کامل)

> **برای Step 12A تا 12F.** این فایل منبع حقیقت طراحی مسابقات است و جایگزین توضیح کوتاه قبلی Step 12 است. قواعد MASTER (به‌ویژه بخش ۴ و ۱۲) اجباری است.
> **اصل:** قرعه، جدول بازی‌ها، رده‌بندی، پیشروی و محاسبه‌ی برنده فقط با **کد قطعی و تست‌شده** است. AI در هیچ‌کدام نقش ندارد.

---

## ۱. هدف و دو سناریوی مرجع

مدیر در هر رویداد یک یا چند **ورزش** (`competition`) تعریف می‌کند، تیم‌ها یا بازیکنان را ثبت می‌کند، قرعه و جدول‌بندی می‌کند، نتیجه‌ها را وارد می‌کند و جدول و رده‌بندی را می‌بیند.

- **المپیاد درون‌دانشگاهی:** رویداد `multi_sport`؛ ورزش‌های فوتسال، والیبال، تنیس روی میز؛ هر ورزش یک مسئول؛ قالب گروهی ← حذفی.
- **لیگ برتر اسکواش:** رویداد `league`؛ دو ورزش «اسکواش آقایان» و «اسکواش بانوان»؛ هرکدام لیگ مستقل؛ مثلاً ۱۵ تیم رفت‌وبرگشتی.

**اصل طراحی: هیچ شکل برگزاری در کد ثابت نیست.** تیمی/انفرادی، قالب مرحله‌ها، قواعد امتیاز و تساوی، و نیاز به تأیید نتیجه، همه **تنظیم هر ورزش**‌اند. همین مدل از مسابقه‌ی یک کلاس مدرسه تا رویداد چندرشته‌ای بزرگ کار می‌کند.

| مقیاس | مثال | چه چیزی استفاده می‌شود |
|---|---|---|
| ساده | مدرسه، بین‌کلاسی | یک ورزش، یک مرحله (دوره‌ای/تک‌حذفی)، بدون تأیید دومرحله‌ای |
| متوسط | المپیاد دانشگاهی، لیگ اسکواش | چند ورزش، گروهی ← حذفی، لیگ رفت‌وبرگشتی، ثبت‌کننده و تأییدکننده‌ی جدا |
| بزرگ | رویداد چندرشته‌ای | ورزش ← رشته (`parent_id`) ← مرحله، نتیجه‌ی رتبه‌ای، جدول مدال |

کاربر ساده هرگز پیچیدگی سطح بزرگ را نمی‌بیند: **قالب‌های آماده** (بخش ۵).

---

## ۲. سلسله‌مراتب

```
Event
 └─ Competition (ورزش)   ← می‌تواند رشته‌ی زیرمجموعه داشته باشد: شنا ← ۱۰۰ متر کرال آقایان (parent_id)
      ├─ Competitor (تیم یا بازیکن) ──< CompetitorMember (ترکیب تیم)
      └─ Stage (مرحله: round_robin | single_elim | double_elim | groups | heats)
           ├─ SportMatch ──< MatchGame (گیم/ست)            [result_type = match]
           └─ Heat ──< HeatResult (زمان/مسافت/وزن + رتبه)    [result_type = ranked]
```
- **جنسیت و رده بخشی از ورزش است، نه رویداد:** «اسکواش آقایان» و «اسکواش بانوان» دو `competition` جدا زیر یک رویداد.
- رویارویی تیمی با چند بازی انفرادی داخلش (مثلاً ۳ بازی تک‌نفره، نتیجه‌ی تیم ۲–۱): بازی پدر = رویارویی تیمی، بازی‌های فرزند با `parent_match_id`؛ رده‌بندی از نتیجه‌ی پدر محاسبه می‌شود.
- Entitlement: feature `competitions` فقط حساب سازمانی. ایجاد ورزش/مرحله/بازی بدون سقف عددی مگر در `plans.php` تعریف شود.

---

## ۳. جدول‌ها (migration قابل‌حمل SQLite/PostgreSQL؛ ستون JSON = `json`)

مدل بازی: **`SportMatch`** روی جدول `matches` (`Match` در PHP 8 رزرو است).

```php
Schema::create('competitions', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->foreignId('event_id')->constrained()->cascadeOnDelete();
    $table->foreignId('parent_id')->nullable()->constrained('competitions')->nullOnDelete(); // رشته‌ی زیر یک ورزش (سطح بزرگ)
    $table->string('name');                          // اسکواش بانوان
    $table->string('sport_code');                    // squash, futsal, athletics_100m
    $table->string('gender')->default('open');       // men | women | mixed | open
    $table->string('participant_type');              // team | individual
    $table->string('result_type')->default('match'); // match | ranked
    $table->json('scoring_config');                  // بخش ۵
    $table->string('status')->default('registration'); // registration|drawn|ready|running|finished
    $table->boolean('requires_confirmation')->default(true);
    $table->unsignedInteger('capacity')->nullable();
    $table->dateTime('registration_opens_at')->nullable();
    $table->dateTime('registration_closes_at')->nullable();
    $table->string('public_token')->nullable()->unique();   // صفحه‌ی عمومی فقط‌خواندنی
    $table->timestamps();
    $table->softDeletes();
});

Schema::create('competition_user', function (Blueprint $table) {   // مسئول ورزش / ثبت‌کننده‌ی نتیجه
    $table->id();
    $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('role');                          // manager | scorer
    $table->timestamps();
    $table->unique(['competition_id', 'user_id']);
});

Schema::create('competition_templates', function (Blueprint $table) {  // قالب‌های آماده (seed؛ دانش قطعی)
    $table->id();
    $table->string('code')->unique();
    $table->string('name_fa');
    $table->string('participant_type');
    $table->string('result_type')->default('match');
    $table->json('scoring_config');
    $table->json('stages');                          // فهرست مرحله‌ها با config
    $table->timestamps();
});

Schema::create('competitors', function (Blueprint $table) {   // تیم یا بازیکن + ثبت‌نام
    $table->id();
    $table->uuid('uuid')->unique();
    $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('affiliation')->nullable();       // باشگاه/دانشکده/مدرسه/استان (برای جداسازی در قرعه و مدال)
    $table->unsignedInteger('seed')->nullable();
    $table->string('status')->default('registered'); // registered | confirmed | withdrawn
    $table->json('documents')->nullable();           // مدارک ثبت‌نام
    $table->unsignedInteger('final_rank')->nullable(); // رتبه‌ی نهایی در ورزش؛ سیستم پر می‌کند
    $table->json('meta')->nullable();
    $table->timestamps();
});

Schema::create('competitor_members', function (Blueprint $table) {   // ترکیب تیم
    $table->id();
    $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->string('full_name');
    $table->string('role')->nullable();              // کاپیتان، بازیکن
    $table->timestamps();
});

Schema::create('stages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('position');
    $table->string('name');
    $table->string('format');                        // round_robin | single_elim | double_elim | groups | heats
    $table->json('config')->nullable();              // legs, fixture_mode, tiebreakers, advance_count, third_place ...
    $table->boolean('is_final')->default(false);     // رتبه‌ی نهایی ورزش از این مرحله می‌آید
    $table->string('status')->default('pending');    // pending | drawn | running | finished
    $table->timestamps();
});

Schema::create('stage_entries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
    $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
    $table->string('group_name')->nullable();        // A, B, ...
    $table->unsignedInteger('seed')->nullable();
    $table->unsignedInteger('draw_position')->nullable(); // شماره‌ی خانه‌ی قرعه ۱..n
    $table->unsignedInteger('final_rank')->nullable();
    $table->unique(['stage_id', 'competitor_id']);
});

Schema::create('matches', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
    $table->foreignId('parent_match_id')->nullable()->constrained('matches')->cascadeOnDelete();
    $table->string('group_name')->nullable();
    $table->unsignedInteger('round');                // در لیگ رفت‌وبرگشت پیوسته ۱..2(n-1)
    $table->unsignedInteger('leg')->default(1);      // 1 رفت، 2 برگشت
    $table->unsignedInteger('number');
    $table->string('bracket')->nullable();           // winners | losers | grand_final
    $table->foreignId('a_id')->nullable()->constrained('competitors')->nullOnDelete();
    $table->foreignId('b_id')->nullable()->constrained('competitors')->nullOnDelete();
    $table->json('a_source')->nullable();            // {"winner_of":"R1M3"} | {"group_rank":["A",1]}
    $table->json('b_source')->nullable();
    $table->foreignId('next_match_id')->nullable()->constrained('matches')->nullOnDelete();
    $table->string('next_slot')->nullable();         // a | b
    $table->foreignId('loser_next_match_id')->nullable()->constrained('matches')->nullOnDelete();
    $table->string('loser_next_slot')->nullable();
    $table->dateTime('scheduled_at')->nullable();
    $table->string('court')->nullable();
    $table->string('status')->default('scheduled');  // scheduled|live|finished|walkover|postponed|cancelled
    $table->foreignId('winner_id')->nullable()->constrained('competitors')->nullOnDelete();
    $table->unsignedInteger('score_a')->nullable();  // گل، یا تعداد گیم/ست برده
    $table->unsignedInteger('score_b')->nullable();
    $table->unsignedInteger('points_a')->nullable(); // مجموع امتیاز ریز گیم‌ها (برای point_diff)
    $table->unsignedInteger('points_b')->nullable();
    $table->string('result_status')->nullable();     // entered | confirmed
    $table->boolean('counts_in_standings')->default(true);
    $table->boolean('is_manual')->default(false);
    $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->index(['stage_id', 'round']);
});

Schema::create('match_games', function (Blueprint $table) {
    $table->id();
    $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
    $table->unsignedInteger('game_no');
    $table->unsignedInteger('points_a');
    $table->unsignedInteger('points_b');
});

Schema::create('heats', function (Blueprint $table) {     // ورزش‌های رتبه‌ای
    $table->id();
    $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->unsignedInteger('position');
    $table->dateTime('scheduled_at')->nullable();
});

Schema::create('heat_results', function (Blueprint $table) {
    $table->id();
    $table->foreignId('heat_id')->constrained()->cascadeOnDelete();
    $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
    $table->decimal('value', 12, 3)->nullable();     // ثانیه، متر، کیلوگرم
    $table->unsignedInteger('rank')->nullable();
    $table->string('status')->default('ok');         // ok | DNS | DNF | DQ
});

Schema::create('draws', function (Blueprint $table) {     // سند قرعه‌کشی
    $table->id();
    $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
    $table->string('method');                        // seeded | random | pots
    $table->string('rng_seed');                      // برای بازتولید و اثبات بی‌طرفی
    $table->json('constraints')->nullable();
    $table->json('result');
    $table->foreignId('performed_by')->constrained('users')->cascadeOnDelete();
    $table->timestamps();
});

Schema::create('competition_notes', function (Blueprint $table) {   // یادداشت/هشدار پیشنهادی AI (Step 18)
    $table->id();
    $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
    $table->string('kind');                          // rule_note | risk_warning
    $table->text('body');
    $table->string('support');                       // documented|inferred|design_suggestion|needs_review
    $table->unsignedBigInteger('source_recommendation_id')->nullable();
    $table->timestamps();
});
```
> `tasks.competition_id` (nullable) در Step 08 یا 12F اضافه می‌شود (بخش ۱۰ `01-DOMAIN-MODEL`).

---

## ۴. وضعیت‌ها (ماشین حالت)

| موجودیت | گردش |
|---|---|
| `competition.status` | `registration → drawn → ready → running → finished` |
| `stage.status` | `pending → drawn → running → finished` |
| `match.status` | `scheduled → live → finished` ؛ `walkover` ؛ `postponed` (همان دور می‌ماند) ؛ `cancelled` |
| `match.result_status` | `null → entered → confirmed` (اگر `requires_confirmation=false`، ثبت مستقیم `confirmed`) |
| `competitor.status` | `registered → confirmed` ؛ `withdrawn` |

**«ready»** یعنی ثبت‌نام بسته، قرعه انجام و جدول ساخته شده؛ و فقط از این وضعیت دکمه‌ی «پیشنهاد آمادگی اجرا» (AI، Step 18) فعال می‌شود.

---

## ۵. قالب‌های آماده و تنظیمات

کاربر «افزودن ورزش» می‌زند و یک **قالب** انتخاب می‌کند؛ پشت صحنه یک `scoring_config` و چند `stage` ساخته می‌شود و بعد قابل تغییر است. قالب‌ها در `competition_templates` seed می‌شوند (متخصص می‌نویسد):

| `code` | کاربرد |
|---|---|
| `school_league` | لیگ دوره‌ای تک‌دور (مدرسه، بین‌کلاسی) |
| `quick_knockout` | تک‌حذفی سریع |
| `group_knockout` | گروهی ← حذفی (المپیاد دانشگاهی) |
| `league_2legs` | لیگ رفت‌وبرگشتی |
| `squash_league` | اسکواش: بهترین از ۵ گیم تا ۱۱، اختلاف ۲ |
| `ranked_heats` | سری‌های رتبه‌ای (دوومیدانی/شنا) |

**`scoring_config` نمونه (اسکواش):**
```json
{
  "unit": "games", "best_of": 5, "points_per_game": 11, "win_by": 2,
  "table_points": {"win": 3, "draw": 0, "loss": 0,
                   "by_score": {"3-0": [3,0], "3-1": [3,0], "3-2": [2,1]}},
  "tiebreakers": ["points", "head_to_head_points", "game_diff", "point_diff", "draw_lots"]
}
```
- `unit` = `goals` (فقط نتیجه‌ی نهایی) | `games` (گیم‌به‌گیم) | `sets` | `value` (رتبه‌ای).
- `by_score`: امتیاز جدول بر اساس نتیجه ("برنده-بازنده" ← [امتیاز برنده، امتیاز بازنده]).
- ملاک‌های تساوی (نام‌های هم‌ارز): `points, wins, diff (goal_diff|game_diff|set_diff), for (goals_for|games_for), point_diff, head_to_head_points, head_to_head_diff, draw_lots`.
- ⚠️ **امتیازدهی لیگ اسکواش واقعی** (مثلاً ۳-۲ = ۲ و ۱ یا ۳ و ۰) و تیمی/انفرادی بودن آن **تصمیم باز** است؛ مقدار seed نمونه است و باید با آیین‌نامه‌ی واقعی عوض شود (`90-PROGRESS.md`).

**`stage.config` نمونه (لیگ ۱۵ تیمی رفت‌وبرگشتی):**
```json
{"format":"round_robin","legs":2,"fixture_mode":"auto","withdrawal_policy":"keep_played",
 "tiebreakers":["points","head_to_head_points","goal_diff","goals_for","draw_lots"]}
```
- `fixture_mode`: `auto` (سیستم کل جدول را می‌سازد) | `manual` (بازی‌ها و نتایج دستی/ایمپورت).
- `withdrawal_policy`: `keep_played` (نتایج برگزارشده می‌ماند، بقیه برد اداری برای حریف) | `void_all` (همه‌ی نتایج آن تیم از جدول حذف). پیش‌فرض را از آیین‌نامه‌ی همان مسابقه بگیر.

---

## ۶. موتورهای قطعی (`app/Services/Competitions/Engine/` — PHP خالص، بدون DB و بدون Laravel)

> **پیاده‌سازی مرجع تست‌شده** در `docs/reference/competition-engines/` موجود است (۴۴ تست، PHP 8.3). در Step 12B این فایل‌ها را به `app/Services/Competitions/Engine/` با namespace `App\Services\Competitions\Engine` منتقل کن، تست‌ها را زیر Pest 5 اجرا کن و `pint` بزن. **قبل از اتکا، دوباره اجرا و تأیید کن**؛ مرجع فقط روی PHPUnit 9.6 اجرا شده است.

| کلاس | وظیفه |
|---|---|
| `SeededRandom` | PRNG قطعی از seed متنی (زنجیره‌ی SHA-256، rejection sampling)؛ پایه‌ی بازتولید قرعه |
| `RoundRobinGenerator` | لیگ دوره‌ای تک‌دور و رفت‌وبرگشتی با روش دایره‌ای |
| `BracketGenerator` | تک‌حذفی با بذر، بای و بازی سوم؛ (دوحذفی در 12F) |
| `DrawEngine` | قرعه‌ی تصادفی با محدودیت هم‌وابسته‌ها، قرعه‌ی بذردار، پات‌بندی، snake |
| `StandingsCalculator` | رده‌بندی با ملاک‌های تساوی قابل تنظیم و رودررو (مینی‌جدول) |
| `GameScoreValidator` | اعتبارسنجی گیم‌به‌گیم و محاسبه‌ی برنده/امتیاز |

### ۶.۱ لیگ دوره‌ای (روش دایره‌ای)
تعداد فرد ← یک «استراحت» (bye) اضافه می‌شود. عنصر اول ثابت، بقیه هر دور یک خانه می‌چرخند. دور برگشت = دور رفت با جای دو تیم عوض‌شده و شماره‌ی دور پیوسته. **اعداد مرجع تست:** ۱۵ تیم رفت‌وبرگشت = ۳۰ دور، هر دور ۷ بازی و ۱ استراحت، **۲۱۰ بازی**، هر تیم ۲۸ بازی و ۲ استراحت، و هر جفت مرتب دقیقاً یک بار. ۱۶ تیم تک‌دور = ۱۵ دور، ۱۲۰ بازی، بدون استراحت.

### ۶.۲ حذفی
اندازه‌ی جدول = توان ۲ بعدی؛ ترتیب بذرها با `seedOrder(8) = [1,8,4,5,2,7,3,6]` (بذر ۱ و ۲ فقط در فینال به هم می‌رسند). بذرهای بزرگ‌تر از n وجود ندارند ← **بای برای بذرهای بالا**؛ برنده‌ی بای مستقیم در دور بعد می‌نشیند. هر بازی `next_match_id` + `next_slot` دارد؛ منبع هر خانه‌ی دور بعد `{"winner_of": key}` است. بازی سوم/چهارم فقط برای n ≥ 4.
**دوحذفی (12F):** هر بازی جدول برنده‌ها `loser_next_match_id` دارد؛ فینال بزرگ با گزینه‌ی «بازی دوم در صورت برد بازنده‌ی جدول بالا».

### ۶.۳ گروهی ← حذفی
تقسیم به گروه با پات‌بندی تصادفی یا snake بر اساس بذر؛ لیگ دوره‌ای داخل هر گروه؛ سپس جدول حذفی با اتصال خودکار (اول A با دوم B و بالعکس)؛ قاعده‌ی «چند نفر صعود کنند» در `stage.config.advance_count`.

### ۶.۴ قرعه‌کشی
ترتیب با PRNG از `rng_seed`؛ seed و نتیجه در `draws` ذخیره می‌شود (قابل بازتولید و ممیزی). محدودیت‌ها: پات‌بندی، بذر، «هم‌وابسته‌ها در دور اول به هم نخورند»؛ اگر محدودیت قابل ارضا نبود **صریح گزارش می‌شود** (`satisfied=false`)، پنهان نمی‌شود. قرعه با **پیش‌نمایش** و فقط با تأیید مدیر قطعی می‌شود. قرعه‌ی دوباره فقط مدیر، و فقط قبل از اولین نتیجه‌ی تأییدشده؛ لاگ می‌شود.

### ۶.۵ رده‌بندی
جدول از بازی‌های تأییدشده با `counts_in_standings=true` **محاسبه** می‌شود (منبع حقیقت جدا ندارد؛ در صورت نیاز کش و با هر تأیید باطل). ستون‌ها: بازی، برد، مساوی، باخت، زده/خورده، تفاضل، امتیاز ریز، امتیاز جدول. **ملاک رودررو:**
1. ردیف‌ها را با معیار اول گروه‌بندی کن؛
2. برای هر گروه مساوی (>۱ عضو) و معیار رودررو: مینی‌جدول **فقط بین همین تیم‌ها با بازی‌های بینشان** بساز و با همان معیار مرتب کن؛
3. اگر گروه کوچک‌تر شد، رودررو را **دوباره فقط روی زیرمجموعه** اجرا کن؛ اگر کوچک نشد، برو معیار بعدی؛
4. آخرین معیار `draw_lots` با seed ثبت‌شده؛ اگر نبود، مساوی‌ماندن با `tied=true` و رتبه‌ی مشترک گزارش می‌شود.

### ۶.۶ پیشروی و ویرایش نتیجه
با تأیید نتیجه، برنده در `next_match` (و در دوحذفی بازنده در `loser_next_match`) می‌نشیند. ویرایش نتیجه‌ای که پیشروی کرده: اگر بازی بعدی هنوز نتیجه ندارد، جایگزینی خودکار؛ اگر دارد، **ویرایش مسدود** با پیام «ابتدا نتیجه‌ی بازی بعدی را لغو کنید».

### ۶.۷ حالت‌های خاص
انصراف (`withdrawn`) طبق `withdrawal_policy`؛ برد اداری (`walkover`)؛ به‌تعویق‌افتادن (`postponed` + تاریخ جدید، همان دور)؛ بازی خارج از جدول (`counts_in_standings=false`، دوستانه/جبرانی).

---

## ۷. جریان کامل: لیگ ۱۵ تیمی رفت‌وبرگشتی

1. مدیر در رویداد «افزودن ورزش» ← قالب `league_2legs` ← نام «اسکواش آقایان» (gender=men).
2. ۱۵ تیم را ثبت می‌کند (دستی یا ایمپورت اکسل) و تأیید می‌کند (`confirmed`).
3. **قرعه:** تیم‌ها در خانه‌های ۱..۱۵ (`draw_position`)؛ `draws` ثبت؛ وضعیت `drawn`.
4. **ساخت جدول (`fixture_mode=auto`):** با یک دکمه ۲۱۰ بازی در ۳۰ دور (Job دسته‌ای)؛ سپس فقط تاریخ/ساعت/زمین تخصیص می‌یابد (دستی یا ایمپورت). وضعیت ورزش ← `ready` (دکمه‌ی صریح مدیر).
5. **ثبت نتیجه در همان صفحه:** باز کردن بازی ← نتیجه (یا گیم‌ها) ← ثبت ← تأیید؛ رده‌بندی فوراً به‌روز.
6. **صفحه‌ی «جدول کامل بازی‌ها»** دو نما: فهرست دور به دور (۳۰ دور) و **ماتریس ۱۵×۱۵** (نتیجه‌ی رفت بالای قطر، برگشت پایین قطر؛ خانه‌ی خالی = انجام‌نشده و کلیک ← فرم ثبت نتیجه).

**حالت دستی (`fixture_mode=manual`):** «افزودن بازی» (دو شرکت‌کننده، دور، تاریخ، زمین، و در صورت تمایل همان لحظه نتیجه)، ایمپورت اکسل بازی‌ها (دور، الف، ب، تاریخ، زمین) و نتیجه‌ها؛ رده‌بندی همان محاسبه. در حالت خودکار هم می‌توان بازی جبرانی دستی اضافه کرد (`is_manual=true`).

**قفل‌ها:** بعد از اولین نتیجه‌ی تأییدشده، ساخت دوباره‌ی جدول و قرعه‌ی دوباره بسته می‌شود. تغییرهای مجاز: جابه‌جایی زمان بازی، افزودن بازی دستی، انصراف تیم.

---

## ۸. ثبت نتیجه و نقش‌ها

- **ثبت‌کننده‌ی نتیجه** (`scorer`، داور/سرداور) نتیجه را وارد می‌کند (`entered`)؛ **مسئول ورزش** (`manager`) تأیید می‌کند (`confirmed`). رده‌بندی و پیشروی فقط از `confirmed`. برای ورزش‌های کوچک `requires_confirmation=false`.
- برای `unit=games` فرم هر گیم را جدا می‌گیرد؛ **برنده و `score_a/score_b` را کد حساب می‌کند** (`GameScoreValidator`: حد امتیاز گیم، اختلاف دو، حداکثر تعداد گیم، بدون گیم بعد از پایان مسابقه).
- هر ثبت/ویرایش/تأیید در `activity_logs`.
- Policy: `enterResult` = مالک/مدیر سازمان یا `event_owner/event_manager` یا عضو `manager|scorer` همان ورزش؛ `confirmResult` همان بدون `scorer`.
- هر route/اکشن این ماژول: تست ۴۰۳ برای کاربر بیرونی، برای `scorer` که تأیید می‌کند، و برای عضو ورزش دیگر.

---

## ۹. ورزش‌های رتبه‌ای و جدول مدال (12E)

`result_type=ranked` (دوومیدانی/شنا): چند نفر با هم، نتیجه یک عدد و رتبه (`heats`/`heat_results`)؛ `scoring_config.better` = `lower|higher`؛ صعود از سری: «n نفر اول هر سری + بهترین‌های باقی‌مانده». پس از پایان مرحله‌ی نهایی، سیستم `competitors.final_rank` را پر می‌کند. **جدول مدال** (یا «کدام مدرسه بیشتر برد»): جمع‌زدن `final_rank = 1|2|3` گروه‌شده بر `affiliation` برای ورزش‌های `finished` یک رویداد؛ امتیاز کل اختیاری با وزن در `events.metadata`.

---

## ۱۰. صفحه‌ی عمومی (12E)

هر ورزش لینک فقط‌خواندنی با `public_token` دارد (جدول، رده‌بندی، نتیجه‌ها) بدون حساب کاربری. محدودیت‌ها: فقط نام و `affiliation`، **هرگز** اطلاعات تماس؛ گزینه‌ی «نمایش مخفف نام» برای زیر ۱۸ سال؛ مدیر هر زمان لینک را غیرفعال یا توکن را عوض کند؛ rate-limit.

---

## ۱۱. صفحه‌های UI (Livewire، موبایل‌اول، RTL)

1. تب «ورزش‌ها» در هاب رویداد + «افزودن ورزش» با انتخاب قالب
2. ثبت‌نام تیم/بازیکن (دستی + اکسل) و ترکیب تیم؛ تأیید ثبت‌نام؛ ظرفیت
3. قرعه‌کشی با پیش‌نمایش و تأیید
4. جدول بازی‌ها (فهرست دور به دور + ماتریس) با کلیک برای ثبت نتیجه
5. رده‌بندی گروه‌ها و جدول حذفی (bracket)
6. فرم سریع ثبت نتیجه + ایمپورت نتیجه
7. صفحه‌ی عمومی
8. زمان‌بندی زمین/ساعت (ابتدا تخصیص دستی؛ خودکار بعداً؛ کنترل تداخل زمان/مکان)
9. جدول مدال

حجم زیاد: صفحه‌بندی، ایندکس مناسب (`matches(stage_id, round)`)، ساخت جدول با Job.

---

## ۱۲. ارتباط با AI (Step 18؛ تکمیلی، نه جایگزین)

پس از `ready`، مدیر «پیشنهاد آمادگی اجرا» را می‌زند: AI از پایگاه دانش (فیلتر `sport_tags`) وظیفه/یادداشت/هشدار پیشنهاد می‌دهد (جلسه‌ی فنی، داوری، آماده‌سازی زمین، ایمنی و پوشش پزشکی، رویه‌ی اعتراض، اعلام نتایج). **عددها** (تعداد دور و بازی، حداقل روز لازم با N زمین، فاصله‌ی استراحت، تعارض‌ها) را **کد** حساب می‌کند و فقط‌خواندنی به AI می‌دهد. AI به `matches`, `draws`, `scoring_config` دست نمی‌زند؛ `rule_note` فقط یادداشت است و تغییر قاعده دستی. جزئیات: `03-KNOWLEDGE-AND-AI.md` بخش ۷.

---

## ۱۳. زیرمرحله‌ها و معیار پایان

| زیرمرحله | محتوا |
|---|---|
| **12A** | `competitions`, `competition_user`, `competition_templates` (+seed)، `competitors`, `competitor_members`؛ UI ورزش‌ها و ثبت‌نام؛ Entitlement و Policy؛ تست tenant |
| **12B** | انتقال موتورها + تست‌ها؛ `stages`, `stage_entries`, `draws`, `matches`؛ **قرعه‌ی لیگ و ساخت جدول دوره‌ای**؛ رده‌بندی؛ تست ۱۵ تیم/۲۱۰ بازی |
| **12C** | ثبت نتیجه (دستی، گیم‌به‌گیم، ایمپورت)، تأیید دومرحله‌ای، حالت `manual`، بازی دستی، انصراف/برد اداری، ماتریس و فهرست دور به دور |
| **12D** | تک‌حذفی (بذر/بای/سوم) و گروهی ← حذفی؛ پیشروی و قفل ویرایش |
| **12E** | صفحه‌ی عمومی، ورزش‌های رتبه‌ای، جدول مدال |
| **12F** | دوحذفی، زمان‌بندی خودکار زمین/ساعت، مرتب‌سازی نهایی و بهینه‌سازی |

**معیار پایان Step 12 (حداقل تا 12D):** یک لیگ ۱۵ تیمی رفت‌وبرگشتی از ثبت‌نام تا آخرین نتیجه (۲۱۰ بازی) و یک رویداد چندورزشی درون‌دانشگاهی با دو یا سه ورزش و مسئول‌های جدا روی سیستم اجرا شود؛ تمام تست‌های موتور و تست‌های دسترسی سبز.

---

## ۱۴. تست‌های اجباری (Pest)

- **موتورها (Unit):** تعداد دور/بازی لیگ (زوج/فرد، ۱۵ تیم = ۲۱۰)؛ هر جفت مرتب یک بار؛ بذر و بای؛ سازگاری `next` با `source`؛ بازتولید قرعه با seed؛ ملاک‌های تساوی (دو و سه‌تیمی و دور باخت/برد) و استقلال از ترتیب ورودی؛ اعتبارسنجی گیم (deuce، ناقص، بازی بعد از پایان)؛ پیشروی و قفل ویرایش.
- **Feature:** سقف/feature حساب، tenant (کاربر سازمان B به ورزش سازمان A دسترسی ندارد)، نقش‌ها (scorer/manager/outsider)، قفل‌ها بعد از اولین نتیجه، انصراف طبق policy، صفحه‌ی عمومی بدون افشای داده‌ی تماس.
