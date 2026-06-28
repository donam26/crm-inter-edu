<x-layouts.app title="Chi nhánh" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Chi nhánh'],
]">
    <x-page-header title="Danh sách chi nhánh">
        @can('create', App\Models\Branch::class)
            <x-button variant="primary" data-modal-form="{{ route('branches.create') }}" data-modal-title="Thêm chi nhánh">
                <x-icon name="plus" class="h-4 w-4" /> Thêm chi nhánh
            </x-button>
        @endcan
    </x-page-header>

    {{-- Filters --}}
    <x-card padding="p-4" class="mb-4">
        <form method="GET" action="{{ route('branches.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="w-64">
                <x-input name="q" label="Tìm kiếm" :value="request('q')" placeholder="Tên hoặc mã" margin="" />
            </div>
            <div class="w-44">
                <x-select name="is_active" label="Trạng thái" placeholder="Tất cả" margin="">
                    <option value="1" @selected(request('is_active') === '1')>Đang hoạt động</option>
                    <option value="0" @selected(request('is_active') === '0')>Ngừng hoạt động</option>
                </x-select>
            </div>
            <x-button type="submit" variant="secondary"><x-icon name="search" class="h-4 w-4" /> Lọc</x-button>
        </form>
    </x-card>

    <x-table :headers="['Mã', 'Tên chi nhánh', 'Quản lý', 'Trạng thái', '']">
        @forelse ($branches as $branch)
            <tr>
                <td class="px-4 py-3 font-mono text-xs">{{ $branch->code }}</td>
                <td class="px-4 py-3">
                    <a href="{{ route('branches.show', $branch) }}" class="font-medium text-gray-900 hover:text-brand-700">{{ $branch->name }}</a>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $branch->manager?->name ?? '—' }}</td>
                <td class="px-4 py-3">
                    <x-badge :variant="$branch->is_active ? 'success' : 'secondary'">
                        {{ $branch->is_active ? 'Hoạt động' : 'Ngừng' }}
                    </x-badge>
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('branches.show', $branch) }}" class="text-sm font-medium text-brand-600 hover:underline">Xem</a>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="5" message="Chưa có chi nhánh nào." icon="building" />
        @endforelse
    </x-table>

    <div class="mt-4">{{ $branches->links() }}</div>
</x-layouts.app>
