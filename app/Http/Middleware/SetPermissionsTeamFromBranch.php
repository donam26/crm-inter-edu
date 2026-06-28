<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Đặt team context của Spatie = branch_id của user đang đăng nhập trên mỗi
 * request. Nhờ vậy mọi kiểm tra role/permission (hasRole, can, @can) đều
 * tự động giới hạn trong phạm vi branch (tenant) của user.
 *
 * Super-admin có branch_id = null → team = null → thấy role/permission toàn cục.
 */
class SetPermissionsTeamFromBranch
{
    public function __construct(private PermissionRegistrar $registrar) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->registrar->setPermissionsTeamId($request->user()?->branch_id);

        return $next($request);
    }
}
