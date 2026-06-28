<x-layouts.app title="Vai trò" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Vai trò'],
]">
    <x-page-header title="Vai trò &amp; phân quyền" subtitle="Quản lý vai trò trong chi nhánh của bạn">
        @can('create', App\Models\Role::class)
            <x-button variant="primary" data-modal-form="{{ route('roles.create') }}" data-modal-title="Thêm vai trò">
                <x-icon name="plus" class="h-4 w-4" /> Thêm vai trò
            </x-button>
        @endcan
    </x-page-header>

    <x-table :headers="['Tên vai trò', 'Phạm vi', 'Số quyền', 'Người dùng', 'Loại', '']">
        @forelse ($roles as $role)
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900">{{ $role->name }}</td>
                <td class="px-4 py-3 text-gray-600">
                    {{ $role->branch?->name ?? 'Toàn cục' }}
                </td>
                <td class="px-4 py-3 tabular-nums">{{ $role->permissions_count }}</td>
                <td class="px-4 py-3 tabular-nums">{{ $role->users_count }}</td>
                <td class="px-4 py-3">
                    <x-badge :variant="$role->is_system ? 'secondary' : 'info'">
                        {{ $role->is_system ? 'Hệ thống' : 'Tùy chỉnh' }}
                    </x-badge>
                </td>
                <td class="px-4 py-3 text-right">
                    @unless ($role->is_system)
                        @can('update', $role)
                            <button type="button"
                                data-modal-form="{{ route('roles.edit', $role) }}"
                                data-modal-title="Sửa vai trò"
                                class="text-sm font-medium text-brand-600 hover:underline">Sửa</button>
                        @endcan
                        @can('delete', $role)
                            <form method="POST" action="{{ route('roles.destroy', $role) }}"
                                onsubmit="return confirm('Xóa vai trò “{{ $role->name }}”?')"
                                class="ml-3 inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-medium text-red-600 hover:underline">Xóa</button>
                            </form>
                        @endcan
                    @else
                        <span class="text-xs text-gray-400">Mặc định</span>
                    @endunless
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="6" message="Chưa có vai trò nào." icon="users" />
        @endforelse
    </x-table>

    <div class="mt-4">{{ $roles->links() }}</div>
</x-layouts.app>
