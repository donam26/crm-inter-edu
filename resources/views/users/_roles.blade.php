{{-- Checkbox vai trò. Với super-admin, lọc theo branch đang chọn (Alpine
     x-data trên form cha cung cấp biến `branch`). Branch-manager chỉ thấy role
     của branch mình (đã lọc sẵn ở controller). --}}
@php($checkedRoles = old('roles', $assignedRoles ?? []))

<div class="mb-1 mt-4">
    <span class="block text-sm font-medium text-gray-700">Vai trò</span>
</div>
<div class="max-h-56 space-y-1.5 overflow-y-auto rounded-lg border border-gray-200 p-3">
    @forelse ($roles as $role)
        <label class="flex items-center text-sm"
            @if ($isSuperAdmin) x-show="branch === '{{ $role->branch_id ?? '' }}'" x-cloak @endif>
            <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                @checked(in_array($role->name, $checkedRoles, true))
                class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
            <span class="ml-2 text-gray-700">{{ $role->name }}</span>
            @if ($isSuperAdmin && $role->branch)
                <span class="ml-2 text-xs text-gray-400">({{ $role->branch->name }})</span>
            @endif
        </label>
    @empty
        <p class="text-sm text-gray-400">Chưa có vai trò khả dụng.</p>
    @endforelse
</div>

@error('roles')
    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
@enderror
@error('roles.*')
    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
@enderror
