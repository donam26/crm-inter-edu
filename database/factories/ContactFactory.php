<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'branch_id' => function (array $attrs) {
                // Lấy branch_id của Customer cha (bypass BranchScope vì factory
                // có thể chạy ngoài request scope, hoặc với user khác branch).
                $customer = Customer::withoutGlobalScopes()->find($attrs['customer_id']);

                return $customer?->branch_id;
            },
            'full_name' => $this->faker->name(),
            'position' => $this->faker->jobTitle(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'is_primary' => false,
            'note' => $this->faker->optional()->sentence(),
        ];
    }

    public function forLead(Customer $customer): self
    {
        return $this->state(fn () => [
            'customer_id' => $customer->id,
            'branch_id' => $customer->branch_id,
        ]);
    }

    public function primary(): self
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
