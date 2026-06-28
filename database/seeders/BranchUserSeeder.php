<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class BranchUserSeeder extends Seeder
{
    /**
     * Tạo user demo cho mỗi branch: 1 branch-manager + 2 sales, gán role trong
     * team context của branch đó. Phải chạy SAU RolePermissionSeeder (role đã có)
     * và TRƯỚC các seeder nghiệp vụ phụ thuộc user (Task/Event/Deal).
     *
     * Mật khẩu mặc định: "password".
     */
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        Branch::all()->each(function (Branch $branch) use ($registrar) {
            // Team context = branch → assignRole đóng dấu branch_id đúng.
            $registrar->setPermissionsTeamId($branch->id);

            $manager = User::firstOrCreate(
                ['email' => "manager{$branch->id}@inter-edu.local"],
                ['name' => "Quản lý {$branch->name}", 'password' => 'password', 'branch_id' => $branch->id],
            );
            if (! $manager->hasRole('branch-manager')) {
                $manager->assignRole('branch-manager');
            }
            $branch->update(['manager_user_id' => $manager->id]);

            foreach (['a', 'b'] as $suffix) {
                $sales = User::firstOrCreate(
                    ['email' => "sales{$branch->id}{$suffix}@inter-edu.local"],
                    ['name' => "Sales {$branch->name} {$suffix}", 'password' => 'password', 'branch_id' => $branch->id],
                );
                if (! $sales->hasRole('sales')) {
                    $sales->assignRole('sales');
                }
            }
        });

        $registrar->setPermissionsTeamId(null);
    }
}
