<?php

namespace App\Support;

use App\Models\User;

/**
 * Single source of truth cho toàn bộ permission của hệ thống.
 *
 * Dùng bởi:
 *  - RolePermissionSeeder (tạo permission + gán cho role mặc định).
 *  - RoleService / UI gán quyền cho role (checkbox theo nhóm module).
 *
 * Quy ước tên permission: `{module}.{action}` (kebab-case action).
 * `{module}.view-all`: xem MỌI bản ghi trong branch; không có → chỉ xem bản
 * ghi của mình (own). Group có `global => true` chỉ super-admin được gán.
 */
class PermissionCatalog
{
    /**
     * @return array<string, array{label: string, global?: bool, permissions: array<string, string>}>
     */
    public static function groups(): array
    {
        return [
            'dashboard' => [
                'label' => 'Dashboard',
                'permissions' => [
                    'dashboard.view' => 'Xem dashboard',
                ],
            ],
            'leads' => [
                'label' => 'Leads',
                'permissions' => [
                    'leads.view' => 'Xem lead',
                    'leads.view-all' => 'Xem mọi lead trong chi nhánh',
                    'leads.create' => 'Tạo lead',
                    'leads.update' => 'Sửa lead',
                    'leads.delete' => 'Xóa lead',
                    'leads.assign' => 'Phân công lead',
                ],
            ],
            'contacts' => [
                'label' => 'Liên hệ',
                'permissions' => [
                    'contacts.view' => 'Xem liên hệ',
                    'contacts.create' => 'Thêm liên hệ',
                    'contacts.update' => 'Sửa liên hệ',
                    'contacts.delete' => 'Xóa liên hệ',
                ],
            ],
            'activities' => [
                'label' => 'Hoạt động',
                'permissions' => [
                    'activities.view' => 'Xem hoạt động',
                    'activities.create' => 'Thêm hoạt động',
                    'activities.update' => 'Sửa hoạt động',
                    'activities.delete' => 'Xóa hoạt động',
                ],
            ],
            'tasks' => [
                'label' => 'Công việc',
                'permissions' => [
                    'tasks.view' => 'Xem công việc',
                    'tasks.view-all' => 'Xem mọi công việc trong chi nhánh',
                    'tasks.create' => 'Tạo công việc',
                    'tasks.update' => 'Sửa công việc',
                    'tasks.delete' => 'Xóa công việc',
                ],
            ],
            'events' => [
                'label' => 'Lịch hẹn',
                'permissions' => [
                    'events.view' => 'Xem lịch hẹn',
                    'events.view-all' => 'Xem mọi lịch hẹn trong chi nhánh',
                    'events.create' => 'Tạo lịch hẹn',
                    'events.update' => 'Sửa lịch hẹn',
                    'events.delete' => 'Xóa lịch hẹn',
                ],
            ],
            'products' => [
                'label' => 'Sản phẩm',
                'permissions' => [
                    'products.view' => 'Xem sản phẩm',
                    'products.create' => 'Tạo sản phẩm',
                    'products.update' => 'Sửa sản phẩm',
                    'products.delete' => 'Xóa sản phẩm',
                ],
            ],
            'deals' => [
                'label' => 'Cơ hội bán hàng',
                'permissions' => [
                    'deals.view' => 'Xem deal',
                    'deals.view-all' => 'Xem mọi deal trong chi nhánh',
                    'deals.create' => 'Tạo deal',
                    'deals.update' => 'Sửa deal',
                    'deals.delete' => 'Xóa deal',
                    'deals.close' => 'Đóng deal (win/lose)',
                ],
            ],
            'invoices' => [
                'label' => 'Hóa đơn',
                'permissions' => [
                    'invoices.view' => 'Xem hóa đơn',
                    'invoices.view-all' => 'Xem mọi hóa đơn trong chi nhánh',
                    'invoices.create' => 'Tạo hóa đơn',
                    'invoices.update' => 'Sửa hóa đơn',
                    'invoices.delete' => 'Xóa hóa đơn',
                    'invoices.issue' => 'Phát hành hóa đơn',
                    'invoices.void' => 'Hủy hóa đơn',
                ],
            ],
            'payments' => [
                'label' => 'Thanh toán',
                'permissions' => [
                    'payments.view' => 'Xem thanh toán',
                    'payments.view-all' => 'Xem mọi thanh toán trong chi nhánh',
                    'payments.create' => 'Ghi nhận thanh toán',
                    'payments.update' => 'Sửa thanh toán',
                    'payments.delete' => 'Xóa thanh toán',
                    'payments.confirm' => 'Xác nhận thanh toán',
                ],
            ],
            'revenues' => [
                'label' => 'Doanh thu',
                'permissions' => [
                    'revenues.view' => 'Xem báo cáo doanh thu',
                ],
            ],
            'users' => [
                'label' => 'Người dùng',
                'permissions' => [
                    'users.view' => 'Xem người dùng',
                    'users.create' => 'Tạo người dùng',
                    'users.update' => 'Sửa người dùng',
                    'users.delete' => 'Xóa người dùng',
                ],
            ],
            'roles' => [
                'label' => 'Vai trò',
                'permissions' => [
                    'roles.view' => 'Xem vai trò',
                    'roles.create' => 'Tạo vai trò',
                    'roles.update' => 'Sửa vai trò',
                    'roles.delete' => 'Xóa vai trò',
                ],
            ],
            'branches' => [
                'label' => 'Chi nhánh',
                'global' => true,
                'permissions' => [
                    'branches.view' => 'Xem chi nhánh',
                    'branches.create' => 'Tạo chi nhánh',
                    'branches.update' => 'Sửa chi nhánh',
                    'branches.delete' => 'Xóa chi nhánh',
                ],
            ],
        ];
    }

    /**
     * Flat list mọi permission name (dùng cho seeder + validation).
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $names = [];
        foreach (self::groups() as $group) {
            foreach (array_keys($group['permissions']) as $name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Permission mà role thuộc branch được phép sở hữu (loại bỏ group global).
     *
     * @return list<string>
     */
    public static function branchAssignable(): array
    {
        $names = [];
        foreach (self::groups() as $group) {
            if ($group['global'] ?? false) {
                continue;
            }
            foreach (array_keys($group['permissions']) as $name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Group hiển thị cho UI tùy actor: super-admin thấy cả group global,
     * người dùng trong branch chỉ thấy group branch-scoped.
     *
     * @return array<string, array{label: string, global?: bool, permissions: array<string, string>}>
     */
    public static function groupsFor(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return self::groups();
        }

        return array_filter(
            self::groups(),
            static fn (array $group) => ! ($group['global'] ?? false)
        );
    }
}
