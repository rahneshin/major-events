<?php

return [
    'title' => 'وظایف',
    'add' => 'وظیفه جدید',
    'created' => 'وظیفه ایجاد شد.',
    'updated' => 'وظیفه به‌روز شد.',
    'deleted' => 'وظیفه حذف شد.',
    'confirm_delete' => 'این وظیفه (و زیروظیفه‌هایش) حذف شود؟',

    'status' => [
        'todo' => 'انجام‌نشده',
        'doing' => 'در حال انجام',
        'blocked' => 'منتظر پیش‌نیاز',
        'done' => 'انجام‌شده',
        'cancelled' => 'لغوشده',
    ],
    'phase' => [
        'pre' => 'پیش از رویداد',
        'during' => 'حین رویداد',
        'post' => 'پس از رویداد',
    ],
    'module' => [
        'strategy' => 'راهبرد',
        'operations' => 'عملیات اجرایی',
        'budget' => 'بودجه',
        'marketing' => 'بازاریابی',
        'sponsorship' => 'اسپانسرینگ',
        'risk' => 'ریسک',
        'logistics' => 'تدارکات و لجستیک',
        'competitions' => 'مسابقات',
        'evaluation' => 'ارزیابی',
    ],
    'priority' => [
        'low' => 'کم',
        'medium' => 'متوسط',
        'high' => 'زیاد',
        'urgent' => 'فوری',
    ],
    'source' => [
        'manual' => 'دستی',
        'ai' => 'پیشنهاد هوش مصنوعی',
    ],
    'milestone_status' => [
        'pending' => 'در انتظار',
        'reached' => 'محقق‌شده',
        'cancelled' => 'لغوشده',
    ],

    // ---- validation / business-rule messages (thrown by TaskService)
    'title_required' => 'عنوان وظیفه الزامی است.',
    'title_too_long' => 'عنوان نباید بیشتر از :max نویسه باشد.',
    'milestone_title_required' => 'عنوان نقطه‌ی عطف الزامی است.',
    'invalid_value' => 'مقدار انتخاب‌شده معتبر نیست.',
    'hours_invalid' => 'برآورد ساعت باید عددی بین ۰ تا ۹۹۹۹٫۹۹ باشد.',
    'date_invalid' => 'تاریخ واردشده معتبر نیست.',
    'due_before_start' => 'موعد نمی‌تواند قبل از زمان شروع باشد.',
    'committee_invalid' => 'کمیته‌ی انتخاب‌شده متعلق به این رویداد نیست.',
    'assignee_invalid' => 'مسئول انتخاب‌شده عضو این رویداد نیست.',
    'assignee_viewer' => 'به «بیننده» نمی‌توان وظیفه سپرد.',
    'parent_invalid' => 'وظیفه‌ی والد معتبر نیست.',
    'subtask_depth' => 'زیروظیفه فقط یک سطح دارد؛ نمی‌توان زیروظیفه‌ی یک زیروظیفه ساخت.',
    'subtask_has_children' => 'این وظیفه خودش زیروظیفه دارد و نمی‌تواند زیروظیفه‌ی دیگری شود.',
    'blocked_is_automatic' => 'وضعیت «منتظر پیش‌نیاز» خودکار تعیین می‌شود و دستی قابل‌انتخاب نیست.',
    'blocked_by_prerequisites' => 'تا پایان پیش‌نیازها نمی‌توان وضعیت این وظیفه را تغییر داد (فقط لغو ممکن است).',

    'dependency_self' => 'یک وظیفه نمی‌تواند پیش‌نیاز خودش باشد.',
    'dependency_other_event' => 'پیش‌نیاز باید از همین رویداد باشد.',
    'dependency_invalid' => 'وظیفه‌ی حذف‌شده را نمی‌توان پیش‌نیاز کرد.',
    'dependency_exists' => 'این پیش‌نیاز قبلاً ثبت شده است.',
    'dependency_cycle' => 'این پیش‌نیاز حلقه‌ی وابستگی می‌سازد (وظایف به‌صورت چرخشی منتظر هم می‌مانند).',

    'comment_required' => 'متن نظر خالی است.',
    'comment_too_long' => 'نظر نباید بیشتر از :max نویسه باشد.',

    'attachment_invalid' => 'فایل نامعتبر یا خالی است.',
    'attachment_too_large' => 'حجم فایل بیشتر از :max مگابایت است.',
    'attachment_type_not_allowed' => 'نوع فایل مجاز نیست (مجاز: PDF، تصویر PNG/JPG/WEBP، TXT، CSV، Word، Excel، PowerPoint).',
    'attachment_store_failed' => 'ذخیره‌ی فایل ناموفق بود.',
];
