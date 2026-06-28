<?php

namespace Database\Factories;

use App\Enums\DealStage;
use App\Models\Branch;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    protected $model = Deal::class;

    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'branch_id' => function (array $attrs) {
                $lead = Lead::withoutGlobalScopes()->find($attrs['lead_id']);

                return $lead?->branch_id ?? (Branch::query()->inRandomOrder()->value('id') ?? Branch::factory());
            },
            'owner_user_id' => null,
            'created_by' => function (array $attrs) {
                $branchId = $attrs['branch_id'];
                $user = User::query()->where('branch_id', $branchId)->inRandomOrder()->first()
                    ?? User::query()->whereNull('branch_id')->inRandomOrder()->first()
                    ?? User::factory()->create();

                return $user->id;
            },
            'code' => 'D-'.$this->faker->unique()->bothify('########'),
            'title' => 'Hợp đồng '.$this->faker->company(),
            'subtotal_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'stage' => $this->faker->randomElement(DealStage::values()),
            'expected_close_date' => $this->faker->optional()->dateTimeBetween('now', '+90 days')?->format('Y-m-d'),
            'actual_close_date' => null,
            'note' => null,
        ];
    }

    public function won(): self
    {
        return $this->state(fn () => [
            'stage' => DealStage::ClosedWon->value,
            'actual_close_date' => now()->toDateString(),
        ]);
    }

    public function open(): self
    {
        return $this->state(fn () => [
            'stage' => DealStage::Negotiation->value,
        ]);
    }

    public function forLead(Lead $lead): self
    {
        return $this->state(fn () => [
            'lead_id' => $lead->id,
            'branch_id' => $lead->branch_id,
            'owner_user_id' => $lead->assigned_user_id,
        ]);
    }
}
