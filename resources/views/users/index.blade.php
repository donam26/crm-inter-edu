<x-layouts.app title="Người dùng" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Người dùng'],
]">
    <x-page-header title="Quản lý người dùng">
        @can('create', App\Models\User::class)
            <x-button variant="primary" data-modal-form="{{ route('users.create') }}" data-modal-title="Thêm người dùng">
                <x-icon name="plus" class="h-4 w-4" /> Thêm người dùng
            </x-button>
        @endcan
    </x-page-header>

    {{-- Filters --}}
    <x-card padding="p-4" class="mb-4">
        <form method="GET" action="{{ route('users.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="w-64">
                <x-input name="q" label="Tìm kiếm" :value="request('q')" placeholder="Tên hoặc email" margin="" />
            </div>
            <x-button type="submit" variant="secondary"><x-icon name="search" class="h-4 w-4" /> Lọc</x-button>
        </form>
    </x-card>

    <x-table :headers="['Email', 'Họ tên', 'Vai trò', 'Chi nhánh', '']">
        @forelse ($users as $u)
            <tr>
                <td class="px-4 py-3">{{ $u->email }}</td>
                <td class="px-4 py-3">
                    <a href="{{ route('users.show', $u) }}" class="font-medium text-gray-900 hover:text-brand-700">{{ $u->name }}</a>
                </td>
                <td class="px-4 py-3">
                    @foreach ($u->roles as $role)
                        <x-badge variant="primary">{{ $role->name }}</x-badge>
                    @endforeach
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $u->branch?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('users.show', $u) }}" class="text-sm font-medium text-brand-600 hover:underline">Xem</a>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="5" message="Chưa có người dùng nào." icon="users" />
        @endforelse
    </x-table>

    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.app>
