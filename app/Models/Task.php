<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Scopes\BranchScope;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'branch_id',
        'lead_id',
        'assigned_user_id',
        'created_by',
        'completed_by',
        'title',
        'description',
        'start_at',
        'type',
        'priority',
        'status',
        'due_at',
        'completed_at',
        'reminder_enabled',
        'remind_at',
    ];

    protected $casts = [
        'type' => TaskType::class,
        'priority' => TaskPriority::class,
        'status' => TaskStatus::class,
        'start_at' => 'datetime',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'remind_at' => 'datetime',
        'reminder_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new BranchScope);
    }

    // ───────────────────── relations ─────────────────────

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest();
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('position');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'label_task');
    }

    // ───────────────────── activity log ─────────────────────

    /**
     * Chỉ ghi audit các field cốt lõi (không log completed_at/by — suy ra từ
     * status). logOnlyDirty: chỉ log khi field đổi thật.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'assigned_user_id', 'priority', 'due_at', 'start_at', 'title'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    // ───────────────────── computed ─────────────────────

    /**
     * Task được coi là overdue khi: chưa hoàn thành / chưa huỷ và due_at đã qua.
     */
    public function isOverdue(): Attribute
    {
        return Attribute::get(function (): bool {
            if (! $this->status instanceof TaskStatus) {
                return false;
            }
            if (! $this->status->isOpen()) {
                return false;
            }

            return $this->due_at !== null && $this->due_at->isPast();
        });
    }

    // ───────────────────── scopes ─────────────────────

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', [
            TaskStatus::Pending->value,
            TaskStatus::InProgress->value,
        ]);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->open()->where('due_at', '<', now());
    }

    public function scopeUpcoming(Builder $q, int $hours = 24): Builder
    {
        return $q->open()
            ->whereBetween('due_at', [now(), now()->addHours($hours)]);
    }

    public function scopeAssignedTo(Builder $q, int $userId): Builder
    {
        return $q->where('assigned_user_id', $userId);
    }
}
