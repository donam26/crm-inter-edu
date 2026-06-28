<?php

namespace Database\Factories;

use App\Models\Deal;
use App\Models\DealItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DealItem>
 */
class DealItemFactory extends Factory
{
    protected $model = DealItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 50);
        $unitPrice = $this->faker->numberBetween(500_000, 3_000_000);
        $discount = 0;
        $taxRate = $this->faker->randomElement([0, 8, 10]);
        $amounts = DealItem::computeAmounts($quantity, $unitPrice, $discount, $taxRate);

        return [
            'deal_id' => Deal::factory(),
            'branch_id' => function (array $attrs) {
                $deal = Deal::withoutGlobalScopes()->find($attrs['deal_id']);

                return $deal?->branch_id;
            },
            'product_id' => null,
            'name' => 'Gói '.$this->faker->word(),
            'description' => null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => $discount,
            'tax_rate' => $taxRate,
            ...$amounts,
            'position' => 0,
        ];
    }

    public function forDeal(Deal $deal): self
    {
        return $this->state(fn () => [
            'deal_id' => $deal->id,
            'branch_id' => $deal->branch_id,
        ]);
    }

    public function forProduct(Product $product): self
    {
        return $this->state(function () use ($product) {
            $quantity = $this->faker->numberBetween(1, 20);
            $unitPrice = (int) $product->unit_price;
            $taxRate = 8;
            $amounts = DealItem::computeAmounts($quantity, $unitPrice, 0, $taxRate);

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                ...$amounts,
            ];
        });
    }
}
