<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskModule;
use App\Enums\TaskPhase;
use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Task extends Model
{
    use SoftDeletes;

    public const DEPENDENCY_FINISH_TO_START = 'finish_to_start';

    protected $fillable = [
        'uuid',
        'event_id',
        'committee_id',
        'parent_id',
        'created_by',
        'title',
        'description',
        'phase',
        'module',
        'assignee_id',
        'status',
        'priority',
        'start_at',
        'due_at',
        'completed_at',
        'estimated_hours',
        'source',
        'ai_recommendation_id',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'phase' => TaskPhase::class,
            'module' => TaskModule::class,
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'source' => TaskSource::class,
            'start_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'estimated_hours' => 'decimal:2',
            'ai_recommendation_id' => 'integer',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Task $task) {
            if (empty($task->uuid)) {
                $task->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // ------------------------------------------------------------ relations

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Tasks that must finish before this one can start (soft-deleted ones are excluded).
     */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'depends_on_task_id')
            ->withPivot('type')
            ->withTimestamps();
    }

    /**
     * Tasks that wait for this one.
     */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'depends_on_task_id', 'task_id')
            ->withPivot('type')
            ->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    // --------------------------------------------------------------- scopes

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            TaskStatus::Todo->value,
            TaskStatus::Doing->value,
            TaskStatus::Blocked->value,
        ]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());
    }

    // -------------------------------------------------------------- helpers

    public function isSubtask(): bool
    {
        return $this->parent_id !== null;
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && ($this->status?->isOpen() ?? false)
            && $this->due_at->isPast();
    }

    /**
     * Prerequisites that are not finished. Only `done` releases a dependent:
     * a cancelled prerequisite keeps blocking it (Decision Log, Step 08).
     */
    public function unmetPrerequisites(): BelongsToMany
    {
        return $this->prerequisites()->where('tasks.status', '!=', TaskStatus::Done->value);
    }

    public function hasUnmetPrerequisites(): bool
    {
        return $this->unmetPrerequisites()->exists();
    }
}
