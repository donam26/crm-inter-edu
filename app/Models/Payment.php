<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Scopes\BranchScope;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'invoice_id',
        'created_by',
        'confirmed_by',
        'code',
        'amount',
        'method',
        'paid_at',
        'confirmed_at',
        'reference_no',
        'note',
    ];

    protected $casts = [
        'method' => PaymentMethod::class,
        'amount' => 'integer',
        'paid_at' => 'date',
        'confirmed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new BranchScope);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }
}
