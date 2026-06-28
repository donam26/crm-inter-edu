<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Database\Factories\DealItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealItem extends Model
{
    /** @use HasFactory<DealItemFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'deal_id',
        'product_id',
        'name',
        'description',
        'quantity',
        'unit_price',
        'discount_amount',
        'tax_rate',
        'line_subtotal',
        'line_tax_amount',
        'line_total',
        'position',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'discount_amount' => 'integer',
        'tax_rate' => 'integer',
        'line_subtotal' => 'integer',
        'line_tax_amount' => 'integer',
        'line_total' => 'integer',
        'position' => 'integer',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Compute thuần — không persist. Service gọi để compute trước khi save.
     *
     * line_subtotal = max(0, qty * unit_price - discount)
     * line_tax      = round(line_subtotal * tax_rate / 100)
     * line_total    = line_subtotal + line_tax
     *
     * @return array{line_subtotal:int,line_tax_amount:int,line_total:int}
     */
    public static function computeAmounts(int $quantity, int $unitPrice, int $discount, int $taxRate): array
    {
        $gross = $quantity * $unitPrice;
        $subtotal = max(0, $gross - $discount);
        $tax = (int) round($subtotal * $taxRate / 100);

        return [
            'line_subtotal' => $subtotal,
            'line_tax_amount' => $tax,
            'line_total' => $subtotal + $tax,
        ];
    }
}
