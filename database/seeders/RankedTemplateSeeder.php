<?php

namespace Database\Seeders;

use App\Models\CompetitionTemplate;
use Illuminate\Database\Seeder;

/**
 * قالب‌های ورزش‌های رتبه‌ای (Step 12E). جدا از CompetitionTemplateSeeder نگه داشته شده تا
 * با قالب‌های حذفی/گروهی (12D) در یک فایل config تداخل نکند. Idempotent: بر اساس `code`.
 */
class RankedTemplateSeeder extends Seeder
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function templates(): array
    {
        $time = ['unit' => 'value', 'value_kind' => 'time', 'better' => 'lower'];

        return [
            'ranked_heats' => [
                'name_fa' => 'سری‌های رتبه‌ای: مقدماتی ← فینال (دوومیدانی / شنا)',
                'participant_type' => 'individual',
                'result_type' => 'ranked',
                'scoring_config' => $time,
                'stages' => [
                    [
                        'name' => 'سری‌های مقدماتی',
                        'format' => 'heats',
                        'is_final' => false,
                        'config' => ['heat_size' => 8, 'advance_per_heat' => 2, 'advance_best_remaining' => 2],
                    ],
                    [
                        'name' => 'فینال',
                        'format' => 'heats',
                        'is_final' => true,
                        'config' => [],
                    ],
                ],
            ],
            'ranked_final' => [
                'name_fa' => 'فینال مستقیم رتبه‌ای (زمان؛ کمتر بهتر)',
                'participant_type' => 'individual',
                'result_type' => 'ranked',
                'scoring_config' => $time,
                'stages' => [
                    ['name' => 'فینال', 'format' => 'heats', 'is_final' => true, 'config' => []],
                ],
            ],
            'ranked_field' => [
                'name_fa' => 'فینال مستقیم رتبه‌ای (مسافت/وزن؛ بیشتر بهتر)',
                'participant_type' => 'individual',
                'result_type' => 'ranked',
                'scoring_config' => ['unit' => 'value', 'value_kind' => 'distance', 'better' => 'higher'],
                'stages' => [
                    ['name' => 'فینال', 'format' => 'heats', 'is_final' => true, 'config' => []],
                ],
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::templates() as $code => $template) {
            CompetitionTemplate::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name_fa' => $template['name_fa'],
                    'participant_type' => $template['participant_type'],
                    'result_type' => $template['result_type'],
                    'scoring_config' => $template['scoring_config'],
                    'stages' => $template['stages'],
                ],
            );
        }
    }
}
