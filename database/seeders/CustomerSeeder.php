<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();

        if ($branches->isEmpty()) {
            return;
        }

        // Trải đều ~20 customer trên các branch hiện có.
        foreach ($branches as $branch) {
            Customer::factory()->count(7)->forBranch($branch)->create();
        }
    }
}
