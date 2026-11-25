<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionTemplate extends Model
{
    protected $fillable = ['code', 'name_fa', 'participant_type', 'result_type', 'scoring_config', 'stages'];

    protected function casts(): array
    {
        return ['scoring_config' => 'array', 'stages' => 'array'];
    }
}
