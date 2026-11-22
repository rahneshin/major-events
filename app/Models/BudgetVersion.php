<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BudgetVersionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetVersion extends Model
{
    protected $fillable = [
        'event_id',
        'number',
        'status',
        'note',
        'snapshot',
        'total_expense',
        'total_revenue',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => BudgetVersionStatus::class,
            'snapshot' => 'array',
            'total_expense' => 'integer',
            'total_revenue' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->status === BudgetVersionStatus::Approved;
    }

    /** تراز مصوب (درآمد − هزینه)؛ null اگر هنوز تأیید نشده. */
    public function balance(): ?int
    {
        if ($this->total_expense === null || $this->total_revenue === null) {
            return null;
        }

        return $this->total_revenue - $this->total_expense;
    }
}
