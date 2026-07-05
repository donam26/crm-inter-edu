<?php

namespace Database\Seeders;

use App\Enums\TaskStatus;
use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        // Bypass BranchScope vì seeder chạy ngoài auth context.
        $customers = Customer::withoutGlobalScopes()->get();

        if ($customers->isEmpty()) {
            return;
        }

        foreach ($customers as $customer) {
            $count = random_int(0, 3);

            for ($i = 0; $i < $count; $i++) {
                Task::factory()->forLead($customer)->create();
            }
        }

        // Một số task không gắn Customer, gán cho user ngẫu nhiên trong branch.
        User::query()
            ->whereNotNull('branch_id')
            ->inRandomOrder()
            ->limit(5)
            ->get()
            ->each(function (User $u) {
                Task::factory()->forUser($u)->status(TaskStatus::Pending)->upcoming()->create();
                Task::factory()->forUser($u)->status(TaskStatus::Pending)->overdue()->create();
            });
    }
}
