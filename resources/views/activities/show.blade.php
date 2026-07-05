<x-layouts.app title="Chi tiết hoạt động" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Khách hàng', 'url' => route('customers.index')],
    ['label' => $activity->customer->name ?? 'Customer', 'url' => route('customers.show', $activity->customer_id)],
    ['label' => $activity->subject],
]">
    <div class="max-w-3xl">
        <x-page-header :title="$activity->subject">
            @can('update', $activity)
                <x-button variant="secondary" data-modal-form="{{ route('activities.edit', $activity) }}" data-modal-title="Sửa hoạt động">Sửa</x-button>
            @endcan
            @can('delete', $activity)
                <form method="POST" action="{{ route('activities.destroy', $activity) }}"
                    onsubmit="return confirm('Xóa hoạt động này?');" class="inline">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger">Xóa</x-button>
                </form>
            @endcan
        </x-page-header>

        <x-card>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <dt class="text-gray-500">Loại</dt>
                    <dd class="mt-0.5"><x-badge variant="primary">{{ $activity->type?->label() }}</x-badge></dd>
                </div>
                <div>
                    <dt class="text-gray-500">Tiêu đề</dt>
                    <dd class="font-medium text-gray-900 mt-0.5">{{ $activity->subject }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Thời gian</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $activity->happened_at?->format('d/m/Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Người tạo</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $activity->user?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Khách hàng</dt>
                    <dd class="mt-0.5">
                        <a href="{{ route('customers.show', $activity->customer_id) }}" class="text-brand-600 hover:underline">
                            {{ $activity->customer?->name }}
                        </a>
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Chi nhánh</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $activity->branch?->name ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-gray-500">Nội dung</dt>
                    <dd class="text-gray-900 mt-0.5 whitespace-pre-line">{{ $activity->content ?? '—' }}</dd>
                </div>
            </dl>
        </x-card>

        <div class="mt-6">
            <a href="{{ route('customers.show', $activity->customer_id) }}" class="inline-flex items-center gap-1 text-sm text-brand-600 hover:underline">
                <x-icon name="arrow-left" class="h-4 w-4" /> Quay lại khách hàng
            </a>
        </div>
    </div>
</x-layouts.app>
