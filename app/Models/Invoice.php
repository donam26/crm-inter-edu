<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Scopes\BranchScope;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'deal_id',
        'created_by',
        'issued_by',
        'voided_by',
        'code',
        'subtotal_amount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'status',
        'issued_at',
        'due_at',
        'voided_at',
        'void_reason',
        'note',
    ];

    protected $casts = [
        'status' => InvoiceStatus::class,
        'subtotal_amount' => 'integer',
        'tax_amount' => 'integer',
        'total_amount' => 'integer',
        'paid_amount' => 'integer',
        'issued_at' => 'date',
        'due_at' => 'date',
        'voided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new BranchScope);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Số tiền còn phải thu. */
    public function balance(): int
    {
        return max(0, (int) $this->total_amount - (int) $this->paid_amount);
    }

    /** Đã quá hạn (status open, due_at đã qua). */
    public function isOverdue(): bool
    {
        if (! $this->status instanceof InvoiceStatus) {
            return false;
        }
        if ($this->status->isFinal() || $this->status === InvoiceStatus::Draft) {
            return false;
        }

        return $this->due_at !== null && $this->due_at->isPast() && $this->balance() > 0;
    }

    public function scopeOutstanding(Builder $q): Builder
    {
        return $q->whereIn('status', [
            InvoiceStatus::Issued->value,
            InvoiceStatus::PartiallyPaid->value,
            InvoiceStatus::Overdue->value,
        ]);
    }

    public function scopeOverdueScope(Builder $q): Builder
    {
        return $q->where('status', InvoiceStatus::Overdue->value);
    }
}
