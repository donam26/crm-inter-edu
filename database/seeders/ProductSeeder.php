<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();

        if ($branches->isEmpty()) {
            return;
        }

        $catalog = [
            ['code' => 'TFL-PRI', 'name' => 'Gói TOEFL Primary', 'unit_price' => 850_000],
            ['code' => 'TFL-JNR', 'name' => 'Gói TOEFL Junior', 'unit_price' => 1_200_000],
            ['code' => 'CAM-YLE', 'name' => 'Gói Cambridge YLE', 'unit_price' => 950_000],
            ['code' => 'IELTS-G', 'name' => 'Gói IELTS General', 'unit_price' => 4_500_000],
            ['code' => 'TOEIC',   'name' => 'Gói TOEIC',          'unit_price' => 1_500_000],
        ];

        foreach ($branches as $branch) {
            foreach ($catalog as $row) {
                Product::factory()->forBranch($branch)->create([
                    'code' => $row['code'].'-'.$branch->id,
                    'name' => $row['name'],
                    'unit_price' => $row['unit_price'],
                ]);
            }
        }
    }
}
