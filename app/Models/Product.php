<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'description',
        'unit_price',
        'is_active',
    ];

    protected $casts = [
        'unit_price' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new BranchScope);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function dealItems(): HasMany
    {
        return $this->hasMany(DealItem::class);
    }
}
