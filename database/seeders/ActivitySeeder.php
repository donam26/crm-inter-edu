<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Customer;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        // Bypass BranchScope vì seeder chạy ngoài auth context.
        Customer::withoutGlobalScopes()->get()->each(function (Customer $customer) {
            $count = random_int(1, 5);

            Activity::factory()
                ->count($count)
                ->forLead($customer)
                ->create();
        });
    }
}
