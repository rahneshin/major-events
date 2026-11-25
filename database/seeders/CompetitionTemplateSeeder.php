<?php

namespace Database\Seeders;

use App\Models\CompetitionTemplate;
use Illuminate\Database\Seeder;

class CompetitionTemplateSeeder extends Seeder
{
    /** Idempotent: بر اساس `code` به‌روز می‌شود. */
    public function run(): void
    {
        foreach ((array) config('competitions.templates', []) as $code => $template) {
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
