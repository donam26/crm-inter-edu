<?php

namespace App\Http\Requests\Role;

use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) ?? false;
    }

    public function rules(): array
    {
        $branchId = $this->user()->isSuperAdmin() ? null : $this->user()->branch_id;

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')->where(
                    fn ($q) => $q->where('branch_id', $branchId)->where('guard_name', 'web'),
                ),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in($this->allowedPermissions())],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Tên vai trò đã tồn tại trong chi nhánh.',
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedPermissions(): array
    {
        return $this->user()->isSuperAdmin()
            ? PermissionCatalog::all()
            : PermissionCatalog::branchAssignable();
    }
}
