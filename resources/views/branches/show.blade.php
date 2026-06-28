<x-layouts.app title="Chi tiết chi nhánh" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Chi nhánh', 'url' => route('branches.index')],
    ['label' => $branch->name],
]">
    <div class="max-w-2xl">
        <x-page-header :title="$branch->name">
            @can('update', $branch)
                <x-button variant="secondary" data-modal-form="{{ route('branches.edit', $branch) }}" data-modal-title="Sửa chi nhánh">Sửa</x-button>
            @endcan
            @can('delete', $branch)
                <form method="POST" action="{{ route('branches.destroy', $branch) }}" onsubmit="return confirm('Xóa chi nhánh này?');" class="inline">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger">Xóa</x-button>
                </form>
            @endcan
        </x-page-header>

        <x-card>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <dt class="text-gray-500">Mã</dt>
                    <dd class="font-mono mt-0.5">{{ $branch->code }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Tên</dt>
                    <dd class="font-medium text-gray-900 mt-0.5">{{ $branch->name }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Quản lý</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $branch->manager?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Địa chỉ</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $branch->address ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Số điện thoại</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $branch->phone ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Trạng thái</dt>
                    <dd class="mt-0.5">
                        <x-badge :variant="$branch->is_active ? 'success' : 'secondary'">
                            {{ $branch->is_active ? 'Hoạt động' : 'Ngừng' }}
                        </x-badge>
                    </dd>
                </div>
            </dl>
        </x-card>

        <div class="mt-6">
            <a href="{{ route('branches.index') }}" class="inline-flex items-center gap-1 text-sm text-brand-600 hover:underline">
                <x-icon name="arrow-left" class="h-4 w-4" /> Quay lại danh sách
            </a>
        </div>
    </div>
</x-layouts.app>
