<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Lead;
use Illuminate\Database\Seeder;

class LeadSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();

        if ($branches->isEmpty()) {
            return;
        }

        // Trải đều ~20 lead trên các branch hiện có.
        foreach ($branches as $branch) {
            Lead::factory()->count(7)->forBranch($branch)->create();
        }
    }
}
