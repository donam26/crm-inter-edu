<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\Event;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $organizer = User::query()
            ->whereNotNull('branch_id')
            ->inRandomOrder()
            ->first();

        $branchId = $organizer?->branch_id;
        $organizerId = $organizer?->id ?? User::factory();

        $startsAt = Carbon::instance(
            $this->faker->dateTimeBetween('-7 days', '+30 days')
        );
        $endsAt = $startsAt->copy()->addMinutes($this->faker->randomElement([30, 45, 60, 90, 120]));

        $isOnline = $this->faker->boolean(40);

        return [
            'branch_id' => $branchId ?? function () use (&$organizerId) {
                $u = User::withoutGlobalScopes()->find(
                    is_int($organizerId) ? $organizerId : null
                );

                return $u?->branch_id;
            },
            'organizer_user_id' => $organizerId,
            'created_by' => $organizerId,
            'customer_id' => null,
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->optional()->paragraph(),
            'type' => $this->faker->randomElement(EventType::values()),
            'status' => EventStatus::Scheduled->value,
            'location' => $isOnline ? null : $this->faker->address(),
            'is_online' => $isOnline,
            'online_url' => $isOnline ? $this->faker->url() : null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'all_day' => false,
            'reminder_at' => $startsAt->copy()->subMinutes(15),
        ];
    }

    public function forBranchUser(User $user): self
    {
        return $this->state(fn () => [
            'organizer_user_id' => $user->id,
            'created_by' => $user->id,
            'branch_id' => $user->branch_id,
        ]);
    }

    public function forLead(Customer $customer): self
    {
        return $this->state(function () use ($customer) {
            $u = User::query()
                ->where('branch_id', $customer->branch_id)
                ->inRandomOrder()
                ->first()
                ?? User::factory()->create(['branch_id' => $customer->branch_id]);

            return [
                'customer_id' => $customer->id,
                'branch_id' => $customer->branch_id,
                'organizer_user_id' => $u->id,
                'created_by' => $u->id,
            ];
        });
    }

    public function status(EventStatus|string $status): self
    {
        $value = $status instanceof EventStatus ? $status->value : $status;

        return $this->state(fn () => ['status' => $value]);
    }

    public function startingAt(Carbon|\DateTimeInterface $startsAt, int $durationMinutes = 60): self
    {
        $start = $startsAt instanceof Carbon ? $startsAt : Carbon::instance($startsAt);

        return $this->state(fn () => [
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes($durationMinutes),
            'reminder_at' => $start->copy()->subMinutes(15),
        ]);
    }
}
