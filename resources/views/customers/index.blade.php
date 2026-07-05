<x-layouts.app title="Lead" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Lead'],
]">
    <x-page-header title="Danh sách lead">
        @can('create', App\Models\Customer::class)
            <x-button variant="primary" data-modal-form="{{ route('customers.create') }}" data-modal-title="Thêm lead">
                <x-icon name="plus" class="h-4 w-4" /> Thêm lead
            </x-button>
        @endcan
    </x-page-header>

    {{-- Filters --}}
    <x-card padding="p-4" class="mb-4">
        <form method="GET" action="{{ route('customers.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="w-44">
                <x-select name="status" label="Trạng thái" placeholder="Tất cả" margin="">
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
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
        ? ['Lead', 'Điện thoại', 'Trạng thái', 'Người phụ trách', 'Chi nhánh', '']
        : ['Lead', 'Điện thoại', 'Trạng thái', 'Người phụ trách', '']">
        @forelse ($customers as $customer)
            <tr>
                <td class="px-4 py-3">
                    <a href="{{ route('customers.show', $customer) }}" class="font-medium text-gray-900 hover:text-brand-700">{{ $customer->name }}</a>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $customer->phone ?? '—' }}</td>
                <td class="px-4 py-3">
                    <x-badge variant="primary" dot>{{ $customer->status?->label() }}</x-badge>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $customer->assignedUser?->name ?? '—' }}</td>
                @if ($isSuperAdmin)
                    <td class="px-4 py-3 text-gray-600">{{ $customer->branch?->name }}</td>
                @endif
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('customers.show', $customer) }}" class="text-sm font-medium text-brand-600 hover:underline">Xem</a>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="$isSuperAdmin ? 6 : 5" message="Chưa có lead nào." icon="customers" />
        @endforelse
    </x-table>

    <div class="mt-4">{{ $customers->links() }}</div>
</x-layouts.app>
