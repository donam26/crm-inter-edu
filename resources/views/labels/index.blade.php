<x-layouts.app title="Nhãn công việc" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Nhãn công việc'],
]">
    <x-page-header title="Nhãn công việc" subtitle="Nhãn phân loại công việc trong chi nhánh của bạn">
        @can('create', App\Models\Label::class)
            <x-button variant="primary" data-modal-form="{{ route('labels.create') }}" data-modal-title="Thêm nhãn">
                <x-icon name="plus" class="h-4 w-4" /> Thêm nhãn
            </x-button>
        @endcan
    </x-page-header>

    <x-table :headers="['Nhãn', 'Màu', 'Chi nhánh', '']">
        @forelse ($labels as $label)
            <tr>
                <td class="px-4 py-3">
                    <x-badge :variant="$label->badgeVariant()">{{ $label->name }}</x-badge>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $label->color }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $label->branch?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-right">
                    @can('update', $label)
                        <button type="button"
                            data-modal-form="{{ route('labels.edit', $label) }}"
                            data-modal-title="Sửa nhãn"
                            class="text-sm font-medium text-brand-600 hover:underline">Sửa</button>
                    @endcan
                    @can('delete', $label)
                        <form method="POST" action="{{ route('labels.destroy', $label) }}"
                            onsubmit="return confirm('Xoá nhãn “{{ $label->name }}”?')" class="ml-3 inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm font-medium text-red-600 hover:underline">Xoá</button>
                        </form>
                    @endcan
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="4" message="Chưa có nhãn nào. Tạo nhãn để phân loại công việc." icon="tasks" />
        @endforelse
    </x-table>

    <div class="mt-4">{{ $labels->links() }}</div>
</x-layouts.app>
