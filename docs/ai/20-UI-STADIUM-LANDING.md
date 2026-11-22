# 20 — UI: صفحه‌ی اصلی «استادیوم مینیاتوری»

> شاخه: `step/08-stadium-landing` (از `step/08-tasks`). فقط فرانت صفحه‌ی اصلی؛ بدون migration، بدون پکیج جدید، بدون تغییر منطق.

## هدف
صفحه‌ی اصلی یک محیط تعاملی ورزشی باشد: استادیوم مینیاتوری که با اسکرول دوربینش حرکت می‌کند و هر صحنه یکی از قابلیت‌های واقعی سامانه را نشان می‌دهد.

## صحنه‌ها (با اسکرول)
| # | صحنه | نمایش |
|---|---|---|
| 0 | نمای هوایی | استادیوم کامل، دکمه‌های انتخاب ورزش |
| 1 | زمین | پین‌های کمیته: فنی، پزشکی، بازاریابی، داوطلبان (همان `CommitteeType`ها) |
| 2 | سکوها | پین‌های نقش: مدیر رویداد، رئیس کمیته، عضو، ناظر (همان `EventRole`ها) |
| 3 | تابلو | تابلوی LED با شمارش معکوس (نمونه) |
| 4 | چراغ‌ها | پرژکتورها روشن؛ فراخوان ورود |

تعامل: انتخاب ورزش (فوتبال/والیبال/بسکتبال) سطح زمین را عوض می‌کند؛ کلیک روی پین توضیح را باز می‌کند؛ نقطه‌های کنار صفحه به صحنه می‌پرند.

## فایل‌ها
- جدید: `resources/views/components/stadium.blade.php`، `resources/views/components/sport-icon.blade.php`، `resources/css/stadium.css`، `resources/js/stadium.js`
- تغییر: `resources/views/public/home.blade.php`، `resources/views/layouts/public.blade.php` (حذف لینک «مراحل کار»)، `resources/css/app.css` (یک `@import`)، `resources/js/app.js` (یک `import`)

## تصمیم‌ها
- نمادهای ورزشی طراحی اختصاصی پروژه‌اند؛ از نشان‌های ثبت‌شده (حلقه‌های المپیک، فیفا و ...) استفاده نشده. لوگوی رسمی فدراسیون/سازمان با مجوز و به‌صورت تصویر جدا در `public/images/brands` اضافه شود.
- CSS/JS استادیوم در فایل جدا تا با تغییرات بعدی `app.css/app.js` تداخل نکند.
- دوربین فقط با متغیرهای CSS (`--rx --rz --s --tx --ty --night`) و یک listener اسکرول با `requestAnimationFrame` کار می‌کند.
- با `prefers-reduced-motion` دوربین بین صحنه‌ها می‌پرد (بدون درون‌یابی) و انتقال‌ها خاموش می‌شوند؛ بدون JavaScript فقط صحنه‌ی اول دیده می‌شود.

## تست دستی
1. `npm run build` (یا `npm run dev`) و باز کردن `/` روی دسکتاپ و موبایل (۳۶۰px).
2. اسکرول: ۵ صحنه به‌ترتیب عوض شوند و پنل متن با صحنه هماهنگ باشد.
3. دکمه‌های فوتبال/والیبال/بسکتبال سطح زمین را عوض کنند.
4. در صحنه‌ی ۱ و ۲ کلیک روی پین توضیح را نشان دهد؛ با تغییر صحنه بسته شود.
5. نقطه‌های سمت چپ/راست صفحه به صحنه بپرند.
6. «کاهش حرکت» در سیستم‌عامل روشن: بدون انیمیشن نرم، فقط پرش بین صحنه‌ها.

## بلوک PROGRESS (در `90-PROGRESS.md` بخش Changelog بچسبان)
```
## UPDATE — UI — صفحه‌ی اصلی استادیوم مینیاتوری — <تاریخ شمسی>
Status: CODE COMPLETE — تست بصری روی مرورگر/موبایل هنوز انجام نشده
Branch: step/08-stadium-landing
Commit: feat(ui): stadium landing with scroll-driven camera and sport pictograms
Files created: [components/stadium.blade.php, components/sport-icon.blade.php, css/stadium.css, js/stadium.js, docs/ai/20-UI-STADIUM-LANDING.md]
Files modified: [public/home.blade.php, layouts/public.blade.php, css/app.css (import), js/app.js (import)]
Migrations: []
New packages: []
Decisions: [نمادهای اختصاصی بدون نشان ثبت‌شده؛ CSS/JS جدا؛ دوربین با CSS variables]
Known issues / TODO: [تنظیم زاویه‌ی دوربین/اندازه‌ی پین‌ها پس از تست بصری؛ پنل کاربر هنوز طرح Lane lines]
Tests: دستی
Next suggested step: بازطراحی پنل کاربر با زبان بصری استادیوم، سپس Step 09
```
