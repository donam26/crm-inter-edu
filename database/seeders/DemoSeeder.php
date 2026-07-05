<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Dữ liệu DEMO/mẫu — chỉ dùng ở môi trường local/dev để có sẵn chi nhánh, người
 * dùng, customer, deal... khi phát triển. TUYỆT ĐỐI không seed lên production (đây
 * là nguồn gốc của "user ảo / gói sản phẩm ảo" mà khách phản ánh).
 *
 * DatabaseSeeder chỉ gọi seeder này khi app()->environment('local').
 * Nếu production đã lỡ có dữ liệu demo, dọn bằng: `php artisan demo:purge`.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BranchSeeder::class,
            // Tạo role branch-manager/sales cho các chi nhánh demo vừa tạo
            // (idempotent — findOrCreate theo team = branch_id).
            RolePermissionSeeder::class,
            BranchUserSeeder::class,
            CustomerSeeder::class,
            ContactSeeder::class,
            ActivitySeeder::class,
            TaskSeeder::class,
            EventSeeder::class,
            ProductSeeder::class,
            DealSeeder::class,
        ]);
    }
}
