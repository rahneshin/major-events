<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BudgetCategory;
use App\Enums\BudgetLineType;
use App\Enums\CostBehavior;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BudgetLine extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'event_id',
        'committee_id',
        'type',
        'category',
        'name',
        'cost_behavior',
        'planned_amount',
        'assumption',
        'version',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => BudgetLineType::class,
            'category' => BudgetCategory::class,
            'cost_behavior' => CostBehavior::class,
            'planned_amount' => 'integer',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BudgetLine $line) {
            if (empty($line->uuid)) {
                $line->uuid = (string) Str::uuid();
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function committee(): BelongsTo
    {
        // کمیته‌ی حذف‌شده (softDelete) هم برای نمایش تاریخچه‌ی بودجه در دسترس بماند
        return $this->belongsTo(Committee::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeForEvent(Builder $query, Event $event): Builder
    {
        return $query->where('budget_lines.event_id', $event->id);
    }

    public function isExpense(): bool
    {
        return $this->type === BudgetLineType::Expense;
    }
}
