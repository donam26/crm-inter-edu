<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Tách 2 tầng để `db:seed` an toàn trên production:
     *  - THIẾT YẾU (mọi môi trường): permission + role super-admin toàn cục +
     *    tài khoản super-admin. RolePermissionSeeder cũng tạo role cho các chi
     *    nhánh đang có (fresh prod chưa có chi nhánh → chỉ tạo phần toàn cục;
     *    role của chi nhánh mới do BranchService cấp khi tạo qua UI).
     *  - DEMO (chỉ local): chi nhánh/người dùng/customer/deal... mẫu. KHÔNG bao giờ
     *    lên production — nếu lỡ có, dọn bằng `php artisan demo:purge`.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SuperAdminSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}
