<?php

namespace App\Models;

use App\Enums\DealStage;
use App\Models\Scopes\BranchScope;
use Database\Factories\DealFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deal extends Model
{
    /** @use HasFactory<DealFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'customer_id',
        'owner_user_id',
        'created_by',
        'code',
        'title',
        'subtotal_amount',
        'tax_amount',
        'total_amount',
        'stage',
        'expected_close_date',
        'actual_close_date',
        'note',
    ];

    protected $casts = [
        'stage' => DealStage::class,
        'subtotal_amount' => 'integer',
        'tax_amount' => 'integer',
        'total_amount' => 'integer',
        'expected_close_date' => 'date',
        'actual_close_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new BranchScope);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DealItem::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** Deal đang mở pipeline (chưa close). */
    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNotIn('stage', [
            DealStage::ClosedWon->value,
            DealStage::ClosedLost->value,
        ]);
    }

    /** Deal đã thắng (đóng góp doanh thu thực tế). */
    public function scopeWon(Builder $q): Builder
    {
        return $q->where('stage', DealStage::ClosedWon->value);
    }
}
