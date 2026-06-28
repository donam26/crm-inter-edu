<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::query()->inRandomOrder()->value('id') ?? Branch::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('PKG-####')),
            'name' => 'Gói khảo thí '.$this->faker->randomElement([
                'TOEFL Junior', 'TOEFL Primary', 'IELTS', 'Cambridge YLE',
                'PTE Young Learners', 'TOEIC',
            ]),
            'description' => $this->faker->optional()->sentence(),
            'unit_price' => $this->faker->numberBetween(500_000, 5_000_000),
            'is_active' => true,
        ];
    }

    public function forBranch(Branch|int $branch): self
    {
        $id = $branch instanceof Branch ? $branch->id : $branch;

        return $this->state(fn () => ['branch_id' => $id]);
    }
}
