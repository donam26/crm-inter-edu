<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\Scopes\BranchScope;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'organizer_user_id',
        'created_by',
        'lead_id',
        'title',
        'description',
        'type',
        'status',
        'location',
        'is_online',
        'online_url',
        'starts_at',
        'ends_at',
        'all_day',
        'reminder_at',
    ];

    protected $casts = [
        'type' => EventType::class,
        'status' => EventStatus::class,
        'is_online' => 'boolean',
        'all_day' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'reminder_at' => 'datetime',
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

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_user')
            ->withPivot(['response', 'responded_at'])
            ->withTimestamps();
    }

    // ───────────────────── computed ─────────────────────

    /**
     * Event được coi là quá hạn (overdue) khi: vẫn ở Scheduled mà ends_at đã qua.
     */
    public function isOverdue(): Attribute
    {
        return Attribute::get(function (): bool {
            if (! $this->status instanceof EventStatus) {
                return false;
            }

            return $this->status === EventStatus::Scheduled
                && $this->ends_at !== null
                && $this->ends_at->isPast();
        });
    }

    public function durationMinutes(): Attribute
    {
        return Attribute::get(function (): int {
            if ($this->starts_at === null || $this->ends_at === null) {
                return 0;
            }

            return max(0, $this->starts_at->diffInMinutes($this->ends_at));
        });
    }

    // ───────────────────── scopes ─────────────────────

    public function scopeBetween(Builder $q, $from, $to): Builder
    {
        // Trùng nửa-mở [from, to): event được coi là chạm khoảng nếu
        // starts_at < to AND ends_at > from.
        return $q->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }

    public function scopeUpcoming(Builder $q, int $hours = 24): Builder
    {
        return $q->where('status', EventStatus::Scheduled->value)
            ->whereBetween('starts_at', [now(), now()->addHours($hours)]);
    }

    public function scopeForOrganizer(Builder $q, int $userId): Builder
    {
        return $q->where('organizer_user_id', $userId);
    }
}
