<?php

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Models\Branch;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::query()->inRandomOrder()->value('id') ?? Branch::factory(),
            'assigned_user_id' => null,
            'name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->safeEmail(),
            'address' => $this->faker->address(),
            'status' => $this->faker->randomElement(CustomerStatus::values()),
            'note' => $this->faker->optional()->sentence(),
        ];
    }

    public function forBranch(Branch|int $branch): self
    {
        $id = $branch instanceof Branch ? $branch->id : $branch;

        return $this->state(fn () => ['branch_id' => $id]);
    }

    public function status(CustomerStatus|string $status): self
    {
        $value = $status instanceof CustomerStatus ? $status->value : $status;

        return $this->state(fn () => ['status' => $value]);
    }
}
