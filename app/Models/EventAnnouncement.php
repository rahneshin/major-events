<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * اطلاعیه‌ی سریع مدیر به اعضای رویداد (مرکز کنترل روز رویداد).
 */
class EventAnnouncement extends Model
{
    protected $fillable = [
        'uuid',
        'event_id',
        'user_id',
        'body',
        'urgent',
        'recipients_count',
    ];

    protected function casts(): array
    {
        return [
            'urgent' => 'boolean',
            'recipients_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EventAnnouncement $announcement) {
            if (empty($announcement->uuid)) {
                $announcement->uuid = (string) Str::uuid();
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
