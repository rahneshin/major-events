<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampaignChannel;
use App\Enums\CampaignStatus;
use App\Enums\KpiMetric;
use App\Enums\TaskPhase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CampaignItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'event_id',
        'phase',
        'channel',
        'title',
        'publish_at',
        'owner_id',
        'status',
        'kpi_metric',
        'kpi_target',
        'kpi_actual',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'phase' => TaskPhase::class,
            'channel' => CampaignChannel::class,
            'status' => CampaignStatus::class,
            'kpi_metric' => KpiMetric::class,
            'publish_at' => 'datetime',
            'kpi_target' => 'integer',
            'kpi_actual' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CampaignItem $item) {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForEvent(Builder $query, Event $event): Builder
    {
        return $query->where('event_id', $event->id);
    }

    /** ترتیب تقویم محتوا: تاریخ انتشار (بدون تاریخ آخر)، سپس شناسه. */
    public function scopeCalendarOrder(Builder $query): Builder
    {
        return $query->orderByRaw('publish_at IS NULL')->orderBy('publish_at')->orderBy('id');
    }

    /** انتشار از تاریخ گذشته ولی هنوز منتشر نشده. */
    public function isLate(): bool
    {
        return $this->publish_at !== null
            && ($this->status?->isOpen() ?? false)
            && $this->publish_at->lt(now());
    }

    /** درصد تحقق KPI (null اگر هدف یا مقدار نباشد). */
    public function kpiPercent(): ?int
    {
        if ($this->kpi_target === null || $this->kpi_target <= 0 || $this->kpi_actual === null) {
            return null;
        }

        return (int) min(999, intdiv((int) $this->kpi_actual * 100, (int) $this->kpi_target));
    }
}
