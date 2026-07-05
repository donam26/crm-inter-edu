<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Models\Scopes\BranchScope;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'assigned_user_id',
        'name',
        'phone',
        'email',
        'address',
        'status',
        'note',
    ];

    protected $casts = [
        'status' => CustomerStatus::class,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new BranchScope);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function contacts(): HasMany
    {
        // Contact model sẽ được tạo ở Module 9 (string-resolved tại runtime).
        return $this->hasMany(Contact::class);
    }

    public function activities(): HasMany
    {
        // Activity model sẽ được tạo ở Module 10 (string-resolved tại runtime).
        return $this->hasMany(Activity::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * 1 Customer = 1 Deal (one-to-one). Quan hệ này được DB enforce qua
     * `unique` trên `deals.customer_id`.
     */
    public function deal(): HasOne
    {
        return $this->hasOne(Deal::class);
    }
}
