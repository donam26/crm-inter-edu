<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'branch_id' => function (array $attrs) {
                $invoice = Invoice::withoutGlobalScopes()->find($attrs['invoice_id']);

                return $invoice?->branch_id;
            },
            'created_by' => function (array $attrs) {
                $branchId = $attrs['branch_id'];
                $user = User::query()->where('branch_id', $branchId)->inRandomOrder()->first()
                    ?? User::factory()->create();

                return $user->id;
            },
            'confirmed_by' => null,
            'code' => 'PAY-'.$this->faker->unique()->bothify('########'),
            'amount' => $this->faker->numberBetween(500_000, 10_000_000),
            'method' => $this->faker->randomElement(PaymentMethod::values()),
            'paid_at' => $this->faker->dateTimeBetween('-30 days')->format('Y-m-d'),
            'confirmed_at' => null,
            'reference_no' => $this->faker->optional()->bothify('REF-####'),
            'note' => null,
        ];
    }

    public function confirmed(): self
    {
        return $this->state(fn (array $attrs) => [
            'confirmed_at' => now(),
            'confirmed_by' => $attrs['created_by'],
        ]);
    }

    public function forInvoice(Invoice $invoice): self
    {
        return $this->state(fn () => [
            'invoice_id' => $invoice->id,
            'branch_id' => $invoice->branch_id,
        ]);
    }
}
