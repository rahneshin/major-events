<?php

/*
|--------------------------------------------------------------------------
| Sponsorship & marketing (Step 11)
|--------------------------------------------------------------------------
|
| due_soon_days: تعهدی که موعدش در این چند روز آینده برسد «نزدیک» حساب می‌شود.
| evidence_max_kb / evidence_mimes: مدرک تحویل تعهد (دیسک خصوصی local).
| max_amount: سقف مبلغ (ریال) برای یک مقدار؛ مجموع‌ها در عدد صحیح ۶۴ بیتی می‌مانند.
| max_inventory: سقف تعداد دارایی/تعهد.
| max_segments / max_segment_length: سقف بخش‌های مخاطب برنامه‌ی بازاریابی.
|
| دسترسی: ویژگی `sponsorship` (فقط حساب سازمانی؛ در config/plans.php ← `*`).
|
*/

return [

    'due_soon_days' => 7,

    'evidence_max_kb' => 5120,

    'evidence_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],

    'max_amount' => 10_000_000_000_000,

    'max_inventory' => 100_000,

    'max_segments' => 12,

    'max_segment_length' => 80,

];
