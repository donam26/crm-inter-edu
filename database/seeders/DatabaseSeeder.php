<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters (multi-tenant teams):
     *  1. BranchSeeder — branches là tenant, cần có trước để seed role theo branch.
     *  2. RolePermissionSeeder — tạo permission + role super-admin (global) và
     *     branch-manager/sales cho từng branch (team = branch_id).
     *  3. SuperAdminSeeder — super-admin (branch_id=null) nhận role super-admin.
     *  4. BranchUserSeeder — user demo (manager/sales) cho mỗi branch.
     *  5. Các seeder nghiệp vụ.
     */
    public function run(): void
    {
        $this->call([
            BranchSeeder::class,
            RolePermissionSeeder::class,
            SuperAdminSeeder::class,
            BranchUserSeeder::class,
            LeadSeeder::class,
            ContactSeeder::class,
            ActivitySeeder::class,
            TaskSeeder::class,
            EventSeeder::class,
            ProductSeeder::class,
            DealSeeder::class,
        ]);
    }
}
