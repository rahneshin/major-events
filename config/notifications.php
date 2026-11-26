<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notification categories (Step 13)
    |--------------------------------------------------------------------------
    |
    | `sms` = this category MAY ever use SMS (cost control). Whether a given
    | notification really goes out by SMS is decided by the notification itself
    | (urgent assignment, due soon, urgent announcement) AND the user's
    | preference AND the daily cap below. In-app is the default channel.
    |
    */

    'categories' => [
        'task_assigned' => ['sms' => true],
        'task_due' => ['sms' => true],
        'committee' => ['sms' => false],
        'announcement' => ['sms' => true],
    ],

    'sms' => [
        'per_user_per_day' => 5,
        'announcement_per_event_per_day' => 3,
        'max_length' => 140,
    ],

    'announcement' => [
        'min_length' => 3,
        'max_length' => 500,
        'per_user_per_hour' => 20,
    ],

    // how many notifications the bell dropdown / center list shows
    'bell_limit' => 6,
    'center_limit' => 60,

];
