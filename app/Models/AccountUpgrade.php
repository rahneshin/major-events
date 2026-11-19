<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountUpgrade extends Model
{
    protected $fillable = [
        'user_id',
        'from_type',
        'to_type',
        'reason',
        'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'from_type' => AccountType::class,
            'to_type' => AccountType::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
