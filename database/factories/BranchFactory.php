<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Campus',
            'code' => strtoupper($this->faker->unique()->bothify('BR-####')),
            'address' => $this->faker->address(),
            'phone' => $this->faker->phoneNumber(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the branch is inactive.
     */
    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
