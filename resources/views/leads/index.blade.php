<x-layouts.app title="Khách hàng tiềm năng" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Khách hàng tiềm năng'],
]">
    <x-page-header title="Danh sách khách hàng tiềm năng">
        @can('create', App\Models\Lead::class)
            <x-button variant="primary" data-modal-form="{{ route('leads.create') }}" data-modal-title="Thêm khách hàng tiềm năng">
                <x-icon name="plus" class="h-4 w-4" /> Thêm khách hàng tiềm năng
            </x-button>
        @endcan
    </x-page-header>

    {{-- Filters --}}
    <x-card padding="p-4" class="mb-4">
        <form method="GET" action="{{ route('leads.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="w-44">
                <x-select name="status" label="Trạng thái" placeholder="Tất cả" margin="">
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </x-select>
            </div>
            <div class="w-44">
                <x-select name="school_level" label="Cấp học" placeholder="Tất cả" margin="">
                    @foreach ($levels as $l)
                        <option value="{{ $l->value }}" @selected(request('school_level') === $l->value)>{{ $l->label() }}</option>
                    @endforeach
                </x-select>
            </div>
            <div class="w-44">
                <x-input name="assigned_user_id" label="Người phụ trách (ID)" :value="request('assigned_user_id')" margin="" />
            </div>
            @if (auth()->user()?->hasRole('super-admin') && $branches->isNotEmpty())
                <div class="w-44">
                    <x-select name="branch_id" label="Chi nhánh" placeholder="Tất cả" margin="">
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected((string) request('branch_id') === (string) $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </x-select>
                </div>
            @endif
            <x-button type="submit" variant="secondary"><x-icon name="search" class="h-4 w-4" /> Lọc</x-button>
        </form>
    </x-card>

    @php $isSuperAdmin = auth()->user()?->hasRole('super-admin'); @endphp

    <x-table :headers="$isSuperAdmin
        ? ['Trường', 'Cấp', 'Trạng thái', 'Người phụ trách', 'Chi nhánh', '']
        : ['Trường', 'Cấp', 'Trạng thái', 'Người phụ trách', '']">
        @forelse ($leads as $lead)
            <tr>
                <td class="px-4 py-3">
                    <a href="{{ route('leads.show', $lead) }}" class="font-medium text-gray-900 hover:text-brand-700">{{ $lead->school_name }}</a>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $lead->school_level?->label() }}</td>
                <td class="px-4 py-3">
                    <x-badge variant="primary" dot>{{ $lead->status?->label() }}</x-badge>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $lead->assignedUser?->name ?? '—' }}</td>
                @if ($isSuperAdmin)
                    <td class="px-4 py-3 text-gray-600">{{ $lead->branch?->name }}</td>
                @endif
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('leads.show', $lead) }}" class="text-sm font-medium text-brand-600 hover:underline">Xem</a>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="$isSuperAdmin ? 6 : 5" message="Chưa có khách hàng tiềm năng nào." icon="leads" />
        @endforelse
    </x-table>

    <div class="mt-4">{{ $leads->links() }}</div>
</x-layouts.app>
