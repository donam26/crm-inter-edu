<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $subtotal = $this->faker->numberBetween(1_000_000, 50_000_000);
        $tax = (int) round($subtotal * 0.08);

        return [
            'deal_id' => Deal::factory(),
            'branch_id' => function (array $attrs) {
                $deal = Deal::withoutGlobalScopes()->find($attrs['deal_id']);

                return $deal?->branch_id ?? (Branch::query()->inRandomOrder()->value('id') ?? Branch::factory());
            },
            'created_by' => function (array $attrs) {
                $branchId = $attrs['branch_id'];
                $user = User::query()->where('branch_id', $branchId)->inRandomOrder()->first()
                    ?? User::query()->whereNull('branch_id')->inRandomOrder()->first()
                    ?? User::factory()->create();

                return $user->id;
            },
            'issued_by' => null,
            'voided_by' => null,
            'code' => 'INV-'.$this->faker->unique()->bothify('########'),
            'subtotal_amount' => $subtotal,
            'tax_amount' => $tax,
            'total_amount' => $subtotal + $tax,
            'paid_amount' => 0,
            'status' => InvoiceStatus::Draft->value,
            'issued_at' => null,
            'due_at' => null,
            'voided_at' => null,
            'void_reason' => null,
            'note' => null,
        ];
    }

    public function issued(): self
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Issued->value,
            'issued_at' => now()->toDateString(),
            'due_at' => now()->addDays(30)->toDateString(),
        ]);
    }

    public function paid(): self
    {
        return $this->state(fn (array $attrs) => [
            'status' => InvoiceStatus::Paid->value,
            'issued_at' => now()->subDays(15)->toDateString(),
            'due_at' => now()->addDays(15)->toDateString(),
            'paid_amount' => $attrs['total_amount'],
        ]);
    }

    public function forDeal(Deal $deal): self
    {
        return $this->state(fn () => [
            'deal_id' => $deal->id,
            'branch_id' => $deal->branch_id,
            'subtotal_amount' => $deal->subtotal_amount,
            'tax_amount' => $deal->tax_amount,
            'total_amount' => $deal->total_amount,
        ]);
    }
}
