<x-layouts.app title="Chi tiết người liên hệ" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Leads', 'url' => route('leads.index')],
    ['label' => $contact->lead->school_name ?? 'Lead', 'url' => route('leads.show', $contact->lead_id)],
    ['label' => $contact->full_name],
]">
    <div class="max-w-3xl">
        <x-page-header :title="$contact->full_name">
            @can('update', $contact)
                <x-button variant="secondary" data-modal-form="{{ route('contacts.edit', $contact) }}" data-modal-title="Sửa người liên hệ">Sửa</x-button>
            @endcan
            @can('delete', $contact)
                <form method="POST" action="{{ route('contacts.destroy', $contact) }}"
                    onsubmit="return confirm('Xóa người liên hệ này?');" class="inline">
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
                    <dd class="font-medium text-gray-900 mt-0.5">
                        {{ $contact->full_name }}
                        @if ($contact->is_primary)
                            <x-badge variant="success">Đầu mối chính</x-badge>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Chức vụ</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $contact->position ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Email</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $contact->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Điện thoại</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $contact->phone ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Lead</dt>
                    <dd class="mt-0.5">
                        <a href="{{ route('leads.show', $contact->lead_id) }}" class="text-brand-600 hover:underline">
                            {{ $contact->lead?->school_name }}
                        </a>
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Chi nhánh</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $contact->branch?->name ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-gray-500">Ghi chú</dt>
                    <dd class="text-gray-900 mt-0.5 whitespace-pre-line">{{ $contact->note ?? '—' }}</dd>
                </div>
            </dl>
        </x-card>

        <div class="mt-6">
            <a href="{{ route('leads.show', $contact->lead_id) }}" class="inline-flex items-center gap-1 text-sm text-brand-600 hover:underline">
                <x-icon name="arrow-left" class="h-4 w-4" /> Quay lại Lead
            </a>
        </div>
    </div>
</x-layouts.app>
