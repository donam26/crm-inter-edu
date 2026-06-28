<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Lead;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        // Bypass BranchScope vì seeder chạy ngoài auth context.
        Lead::withoutGlobalScopes()->get()->each(function (Lead $lead) {
            $count = random_int(1, 5);

            Activity::factory()
                ->count($count)
                ->forLead($lead)
                ->create();
        });
    }
}
