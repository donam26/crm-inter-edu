<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'branch_id' => function (array $attrs) {
                // Lấy branch_id từ Lead cha (bypass BranchScope vì factory
                // có thể chạy ngoài request scope).
                $lead = Lead::withoutGlobalScopes()->find($attrs['lead_id']);

                return $lead?->branch_id;
            },
            'user_id' => function (array $attrs) {
                // Ưu tiên user thuộc cùng branch để dữ liệu seed nhất quán.
                $branchId = $attrs['branch_id'] ?? null;
                $user = User::query()
                    ->when($branchId, fn ($q, $bid) => $q->where('branch_id', $bid))
                    ->inRandomOrder()
                    ->first();

                return $user?->id ?? User::factory();
            },
            'type' => $this->faker->randomElement(ActivityType::values()),
            'subject' => $this->faker->sentence(4),
            'content' => $this->faker->optional()->paragraph(),
            'happened_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function forLead(Lead $lead): self
    {
        return $this->state(function () use ($lead) {
            $user = User::query()
                ->where('branch_id', $lead->branch_id)
                ->inRandomOrder()
                ->first()
                ?? User::query()->inRandomOrder()->first();

            return [
                'lead_id' => $lead->id,
                'branch_id' => $lead->branch_id,
                'user_id' => $user?->id ?? User::factory(),
            ];
        });
    }
}
