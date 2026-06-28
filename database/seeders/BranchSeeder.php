<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Seed the application's branches table with sample branches.
     */
    public function run(): void
    {
        Branch::factory()->count(3)->create();
    }
}
