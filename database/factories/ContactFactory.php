<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Lead;
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
            'lead_id' => Lead::factory(),
            'branch_id' => function (array $attrs) {
                // Lấy branch_id của Lead cha (bypass BranchScope vì factory
                // có thể chạy ngoài request scope, hoặc với user khác branch).
                $lead = Lead::withoutGlobalScopes()->find($attrs['lead_id']);

                return $lead?->branch_id;
            },
            'full_name' => $this->faker->name(),
            'position' => $this->faker->jobTitle(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'is_primary' => false,
            'note' => $this->faker->optional()->sentence(),
        ];
    }

    public function forLead(Lead $lead): self
    {
        return $this->state(fn () => [
            'lead_id' => $lead->id,
            'branch_id' => $lead->branch_id,
        ]);
    }

    public function primary(): self
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
