# بلوک به‌روزرسانی PROGRESS — Step 12A + 12B (مسابقات: ثبت‌نام، قرعه، لیگ دوره‌ای، رده‌بندی)

> این فایل جدا نوشته شده تا با تغییرات Step 11 روی `90-PROGRESS.md` تداخل نگیرد.
> بعد از merge، محتوای آن را به‌صورت یک بلوک «UPDATE» در بالای `90-PROGRESS.md` و ردیف Step 12 جدول وضعیت قرار بده.

شاخه‌ی پایه: `step/10-final-calender` (`d1f4aff`) — شاخه‌ی پیشنهادی: `step/12-competitions`

## وضعیت
| زیرمرحله | وضعیت |
|---|---|
| 12A پایه‌ی داده، قالب‌ها، ثبت‌نام، نقش‌ها، Policy | ✅ نوشته شد — هنوز اجرا/تست نشده |
| 12B قرعه (تصادفی/سید)، ساخت جدول لیگ، رده‌بندی | ✅ نوشته شد — هنوز اجرا/تست نشده |
| 12C ثبت نتیجه، تأیید، انصراف/برد اداری، ایمپورت، زمان‌بندی دستی | ⏳ بعدی |
| 12D حذفی تک‌شکست، گروهی ← حذفی، قفل ویرایش | ⏳ |
| 12E سری‌ها (رتبه‌ای)، صفحه‌ی عمومی، جدول مدال | ⏳ |
| 12F حذفی دوشکست، زمان‌بندی خودکار زمین/ساعت، رتبه‌ی نهایی، بهینه‌سازی | ⏳ |

> ⚠️ هیچ migration/تست/PHP‌ای در محیط نویسنده اجرا نشد. اولین اجرای واقعی با Amir است.

## فایل‌های جدید
- migration: `2026_11_28_000060_create_competitions_tables`، `2026_11_28_000061_create_stage_and_match_tables`
  (قالب‌ها در همین migration با `CompetitionTemplateSeeder` ساخته می‌شوند؛ idempotent)
- `config/competitions.php` (قالب‌ها: `school_league`، `league_2legs`، `squash_league`)
- Enums: `CompetitionStatus`, `StageFormat`, `StageStatus`, `ParticipantType`, `CompetitionGender`, `CompetitorStatus`, `MatchStatus`, `ResultStatus`, `CompetitionRole`
- Models: `Competition`, `CompetitionTemplate`, `Competitor`, `CompetitorMember`, `Stage`, `StageEntry`, `Draw`, `SportMatch` (جدول `matches`)
- موتورهای خالص (`app/Services/Competitions/Engine`): `SeededRandom`, `RoundRobinGenerator`, `DrawEngine`, `StandingsCalculator`
- سرویس‌ها: `CompetitionService`, `RegistrationService`, `DrawService`, `StandingsService`
- `CompetitionPolicy`
- Livewire: `Events\EventCompetitions` (تب)، `Competitions\CompetitionShow` (صفحه‌ی ورزش)
- `lang/fa/competition.php`
- تست‌ها: `tests/Unit/Competitions/*` (۴ فایل)، `tests/Feature/Competitions/*` (۲ فایل)، `tests/Support/CompetitionTestHelpers.php`

## ویرایش فایل‌های موجود (فقط ۳ ویرایش کوچک)
- `routes/web.php`: `use` + مسیر `competitions.show` (`/events/{event}/competitions/{competition}`)
- `app/Livewire/Events/EventShow.php`: افزودن `competitions` به فهرست تب‌های مجاز در query
- `resources/views/livewire/events/event-show.blade.php`: برچسب تب + بلوک `@elseif`

## تصمیم‌های اتخاذشده (نیازمند تأیید)
1. **سنجش طرح روی سازنده‌ی رویداد است** (`event.creator` باید `competitions` داشته باشد) نه کاربر فعلی؛ تا ثبت‌کننده‌ی نتیجه با حساب عادی هم بتواند در رویداد سازمانی کار کند. حساب عادی: تب فقط پیام ارتقا نشان می‌دهد.
2. مسیر ورزش با `{competition}` بر اساس `uuid` و **بررسی دستی تعلق به رویداد** (۴۰۴ در غیر این صورت) — چون `Event::competitions()` عمداً اضافه نشد (کاهش تداخل با Step 11).
3. ساخت جدول با درج دسته‌ای (۱۰۰تایی) داخل تراکنش است، نه Job (۲۱۰ بازی سنگین نیست).
4. `lots_seed` هنگام تأیید قرعه در `stage.config` ثبت می‌شود تا معیار `draw_lots` رده‌بندی قابل‌بازتولید باشد.
5. انصراف بعد از قرعه فعلاً رد می‌شود؛ سیاست‌های `keep_played/void_all` در 12C.
6. ⚠️ امتیازدهی `squash_league` نمونه است (تصمیم باز آیین‌نامه).
7. ایمپورت: در 12C فقط متن/CSV چسبانده‌شده؛ فایل Excel خارج از این مرحله.
8. `competition_notes` و پیوند `tasks.competition_id` در Step 18 / 12F.

## ردیف‌های تست
- Unit: SeededRandom (۵)، RoundRobinGenerator (۵؛ ۱۵ تیم رفت‌وبرگشت = ۲۱۰ بازی / ۳۰ دور)، DrawEngine (۴)، StandingsCalculator (۸؛ رودررو، تساوی، قرعه، برد اداری، جدول امتیاز بر اساس نتیجه)
- Feature: CompetitionFlowTest (۲۰)، CompetitionAccessTest (۱۶)

## دستورهای بعد از apply
```
php artisan migrate
php artisan test --filter=Competitions
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
```
(در صورت تغییر قالب‌ها: `php artisan db:seed --class=CompetitionTemplateSeeder`)

## قدم بعدی
12C: `MatchService` (ثبت/تأیید/ویرایش نتیجه، گیم‌به‌گیم با `GameScoreValidator`، برد اداری، انصراف)، پنل نتایج و ماتریس لیگ، ایمپورت CSV.
