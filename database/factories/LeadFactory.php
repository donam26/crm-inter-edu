<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Enums\SchoolLevel;
use App\Models\Branch;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::query()->inRandomOrder()->value('id') ?? Branch::factory(),
            'assigned_user_id' => null,
            'school_name' => 'Trường '.$this->faker->lastName().' '.$this->faker->randomElement(['THCS', 'THPT', 'TH']),
            'school_level' => $this->faker->randomElement(SchoolLevel::values()),
            'student_size' => $this->faker->numberBetween(50, 2000),
            'address' => $this->faker->address(),
            'status' => $this->faker->randomElement(LeadStatus::values()),
            'note' => $this->faker->optional()->sentence(),
        ];
    }

    public function forBranch(Branch|int $branch): self
    {
        $id = $branch instanceof Branch ? $branch->id : $branch;

        return $this->state(fn () => ['branch_id' => $id]);
    }

    public function status(LeadStatus|string $status): self
    {
        $value = $status instanceof LeadStatus ? $status->value : $status;

        return $this->state(fn () => ['status' => $value]);
    }
}
