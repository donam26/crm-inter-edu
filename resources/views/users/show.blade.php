<x-layouts.app title="Chi tiết người dùng" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Người dùng', 'url' => route('users.index')],
    ['label' => $user->name],
]">
    <div class="max-w-2xl">
        <x-page-header :title="$user->name">
            @can('update', $user)
                <x-button variant="secondary" data-modal-form="{{ route('users.edit', $user) }}" data-modal-title="Sửa người dùng">Sửa</x-button>
            @endcan
            @can('delete', $user)
                <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Xóa người dùng này?');" class="inline">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger">Xóa</x-button>
                </form>
            @endcan
        </x-page-header>

        <x-card>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <dt class="text-gray-500">Họ tên</dt>
                    <dd class="font-medium text-gray-900 mt-0.5">{{ $user->name }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Email</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $user->email }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Vai trò</dt>
                    <dd class="mt-0.5">
                        @forelse ($user->roles as $role)
                            <x-badge variant="primary">{{ $role->name }}</x-badge>
                        @empty
                            —
                        @endforelse
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Chi nhánh</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $user->branch?->name ?? '—' }}</dd>
                </div>
            </dl>
        </x-card>

        <div class="mt-6">
            <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1 text-sm text-brand-600 hover:underline">
                <x-icon name="arrow-left" class="h-4 w-4" /> Quay lại danh sách
            </a>
        </div>
    </div>
</x-layouts.app>
