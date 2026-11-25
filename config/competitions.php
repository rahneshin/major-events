<?php

/*
|--------------------------------------------------------------------------
| Step 12 — قالب‌های آماده‌ی مسابقه (منبع seed جدول competition_templates)
|--------------------------------------------------------------------------
| ⚠️ امتیازدهی لیگ اسکواش واقعی (مثلاً ۳-۲ = ۲ و ۱ یا ۳ و ۰) تصمیم باز است؛
| مقدار `squash_league` نمونه است و باید با آیین‌نامه‌ی واقعی عوض شود.
| بعد از تغییر: php artisan db:seed --class=CompetitionTemplateSeeder
*/

$tiebreakers = ['points', 'head_to_head_points', 'diff', 'for', 'draw_lots'];

return [
    // سقف طول فهرست‌ها و تعداد (کارایی)
    'max_competitors' => 256,

    'templates' => [
        'school_league' => [
            'name_fa' => 'لیگ دوره‌ای تک‌دور (مدرسه / بین‌کلاسی)',
            'participant_type' => 'team',
            'result_type' => 'match',
            'scoring_config' => [
                'unit' => 'goals',
                'table_points' => ['win' => 3, 'draw' => 1, 'loss' => 0],
                'tiebreakers' => $tiebreakers,
            ],
            'stages' => [
                [
                    'name' => 'لیگ',
                    'format' => 'round_robin',
                    'is_final' => true,
                    'config' => ['legs' => 1, 'fixture_mode' => 'auto', 'withdrawal_policy' => 'keep_played'],
                ],
            ],
        ],
        'league_2legs' => [
            'name_fa' => 'لیگ رفت‌وبرگشتی',
            'participant_type' => 'team',
            'result_type' => 'match',
            'scoring_config' => [
                'unit' => 'goals',
                'table_points' => ['win' => 3, 'draw' => 1, 'loss' => 0],
                'tiebreakers' => $tiebreakers,
            ],
            'stages' => [
                [
                    'name' => 'لیگ رفت‌وبرگشت',
                    'format' => 'round_robin',
                    'is_final' => true,
                    'config' => ['legs' => 2, 'fixture_mode' => 'auto', 'withdrawal_policy' => 'keep_played'],
                ],
            ],
        ],
        'squash_league' => [
            'name_fa' => 'لیگ اسکواش (بهترین از ۵ گیم تا ۱۱)',
            'participant_type' => 'individual',
            'result_type' => 'match',
            'scoring_config' => [
                'unit' => 'games',
                'best_of' => 5,
                'points_per_game' => 11,
                'win_by' => 2,
                'table_points' => [
                    'win' => 3, 'draw' => 0, 'loss' => 0,
                    'by_score' => ['3-0' => [3, 0], '3-1' => [3, 0], '3-2' => [2, 1]],
                ],
                'tiebreakers' => ['points', 'head_to_head_points', 'diff', 'point_diff', 'draw_lots'],
            ],
            'stages' => [
                [
                    'name' => 'لیگ',
                    'format' => 'round_robin',
                    'is_final' => true,
                    'config' => ['legs' => 1, 'fixture_mode' => 'auto', 'withdrawal_policy' => 'keep_played'],
                ],
            ],
        ],
    ],
];
