<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Task attachments (Step 08a)
    |--------------------------------------------------------------------------
    |
    | Files live on a PRIVATE disk and are only served through a route that
    | checks TaskPolicy@view. The per-event total is a plan limit
    | (config/plans.php → attachment_mb_per_event); only the per-file cap
    | and the allowed types are defined here.
    |
    | `allowed` maps extension => accepted (server-detected) MIME types.
    | No svg/html/zip/executables on purpose (stored XSS / malware vectors).
    |
    */

    'attachments' => [
        'disk' => 'local',
        'max_kb' => 5120, // 5 MB per file

        'allowed' => [
            'pdf' => ['application/pdf'],
            'png' => ['image/png'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'webp' => ['image/webp'],
            'txt' => ['text/plain'],
            'csv' => ['text/csv', 'text/plain', 'application/csv'],
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
            ],
            'xlsx' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
            ],
            'pptx' => [
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/zip',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reminders (Step 08c) — `php artisan tasks:remind`, scheduled daily 08:00
    |--------------------------------------------------------------------------
    */

    'reminder_days' => 1, // remind when a task is due within N days

    /*
    |--------------------------------------------------------------------------
    | Lists (Step 08b)
    |--------------------------------------------------------------------------
    */

    'list_limit' => 300, // max rows rendered in list/board (use filters beyond this)

];
