<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Role mở rộng của Spatie cho mô hình multi-tenant.
 *
 * - `branch_id` (team_foreign_key): NULL = role toàn cục (super-admin),
 *   ngược lại = role thuộc một branch cụ thể. Spatie tự gán giá trị này
 *   từ team context (xem SetPermissionsTeamFromBranch) khi tạo role.
 * - `is_system`: role mặc định (super-admin/branch-manager/sales) không cho
 *   sửa tên/xóa qua UI.
 */
class Role extends SpatieRole
{
    protected $casts = [
        'is_system' => 'boolean',
    ];

    /**
     * Branch sở hữu role (null với role toàn cục).
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
