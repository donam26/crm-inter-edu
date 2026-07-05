<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        $assignee = User::query()
            ->whereNotNull('branch_id')
            ->inRandomOrder()
            ->first();

        $branchId = $assignee?->branch_id;
        $assigneeId = $assignee?->id ?? User::factory();

        $dueAt = $this->faker->dateTimeBetween('-3 days', '+14 days');
        $status = $this->faker->randomElement(TaskStatus::values());
        $isCompleted = $status === TaskStatus::Completed->value;
        // Khi task đã hoàn thành: completed_at phải ≥ due_at và ≤ now nếu
        // due_at trong quá khứ. Nếu due_at trong tương lai (random sinh ra),
        // ta neo completed_at = now để giữ invariant không vi phạm.
        $completedAt = null;
        if ($isCompleted) {
            $now = new \DateTimeImmutable;
            $completedAt = $dueAt > $now
                ? $now
                : $this->faker->dateTimeBetween($dueAt, 'now');
        }

        return [
            'branch_id' => $branchId ?? function () use (&$assigneeId) {
                $u = User::withoutGlobalScopes()->find(
                    is_int($assigneeId) ? $assigneeId : null
                );

                return $u?->branch_id;
            },
            'customer_id' => null,
            'assigned_user_id' => $assigneeId,
            'created_by' => $assigneeId,
            'completed_by' => $isCompleted ? $assigneeId : null,
            'completed_at' => $completedAt,
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->optional()->paragraph(),
            'type' => $this->faker->randomElement(TaskType::values()),
            'priority' => $this->faker->randomElement(TaskPriority::values()),
            'status' => $status,
            'due_at' => $dueAt,
            'reminder_enabled' => false,
            'remind_at' => null,
        ];
    }

    public function forLead(Customer $customer): self
    {
        return $this->state(function () use ($customer) {
            $user = User::query()
                ->where('branch_id', $customer->branch_id)
                ->inRandomOrder()
                ->first()
                ?? User::factory()->create(['branch_id' => $customer->branch_id]);

            return [
                'customer_id' => $customer->id,
                'branch_id' => $customer->branch_id,
                'assigned_user_id' => $user->id,
                'created_by' => $user->id,
            ];
        });
    }

    public function forUser(User $user): self
    {
        return $this->state(fn () => [
            'assigned_user_id' => $user->id,
            'branch_id' => $user->branch_id,
            'created_by' => $user->id,
        ]);
    }

    public function status(TaskStatus|string $status): self
    {
        $value = $status instanceof TaskStatus ? $status->value : $status;

        return $this->state(function (array $attrs) use ($value) {
            $isCompleted = $value === TaskStatus::Completed->value;

            return [
                'status' => $value,
                'completed_at' => $isCompleted ? ($attrs['completed_at'] ?? now()) : null,
                'completed_by' => $isCompleted
                    ? ($attrs['completed_by'] ?? $attrs['assigned_user_id'] ?? null)
                    : null,
            ];
        });
    }

    public function priority(TaskPriority|string $priority): self
    {
        $value = $priority instanceof TaskPriority ? $priority->value : $priority;

        return $this->state(fn () => ['priority' => $value]);
    }

    public function overdue(): self
    {
        return $this->state(fn () => [
            'status' => TaskStatus::Pending->value,
            'due_at' => now()->subDays(2),
            'completed_at' => null,
            'completed_by' => null,
        ]);
    }

    public function upcoming(): self
    {
        return $this->state(fn () => [
            'status' => TaskStatus::Pending->value,
            'due_at' => now()->addHours(6),
            'completed_at' => null,
            'completed_by' => null,
        ]);
    }
}
