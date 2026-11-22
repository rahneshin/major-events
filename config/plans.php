<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Account plan limits and features
    |--------------------------------------------------------------------------
    |
    | null limit = unlimited
    | features: list of feature keys; ['*'] means all features allowed
    |
    */

    'individual' => [
        'limits' => [
            'active_events' => 1,
            'committees_per_event' => 3,
            'members_per_event' => 10,
            'tasks_per_event' => 50,
            'attachment_mb_per_event' => 20,
            'organizations' => 0,
            'knowledge_ai_requests_per_month' => 0,
        ],
        'features' => [
            'tasks_basic',
            'budget_basic',
            'risk_basic',
            'export_pdf',
        ],
    ],

    'organizational' => [
        'limits' => [
            'active_events' => null,
            'committees_per_event' => null,
            'members_per_event' => null,
            'tasks_per_event' => null,
            'attachment_mb_per_event' => null,
            'organizations' => 3,
            'knowledge_ai_requests_per_month' => 100,
        ],
        'features' => ['*'],
    ],

];
