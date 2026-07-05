<x-layouts.app title="Cơ hội bán hàng" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Cơ hội bán hàng'],
]">
    <x-page-header title="Cơ hội bán hàng">
        @can('create', App\Models\Deal::class)
            <x-button variant="primary" data-modal-form="{{ route('deals.create') }}" data-modal-title="Tạo deal">
                <x-icon name="plus" class="h-4 w-4" /> Tạo deal
            </x-button>
        @endcan
    </x-page-header>

    {{-- Filters --}}
    <x-card padding="p-4" class="mb-4">
        <form method="GET" action="{{ route('deals.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="w-64">
                <x-input name="q" label="Tìm kiếm" :value="request('q')" placeholder="Tiêu đề hoặc mã" margin="" />
            </div>
            <div class="w-44">
                <x-select name="stage" label="Stage" placeholder="Tất cả" margin="">
                    @foreach ($stages as $s)
                        <option value="{{ $s->value }}" @selected(request('stage') === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </x-select>
            </div>
            @if($branchUsers->isNotEmpty())
                <div class="w-44">
                    <x-select name="owner_user_id" label="Người phụ trách" placeholder="Tất cả" margin="">
                        @foreach($branchUsers as $u)
                            <option value="{{ $u->id }}" @selected(request('owner_user_id') == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </x-select>
                </div>
            @endif
            <x-button type="submit" variant="secondary"><x-icon name="search" class="h-4 w-4" /> Lọc</x-button>
        </form>
    </x-card>

    <x-table :headers="['Mã', 'Tiêu đề', 'Lead', 'Stage', 'Giá trị', 'Phụ trách', '']">
        @forelse ($deals as $d)
            <tr>
                <td class="px-4 py-3">
                    <a href="{{ route('deals.show', $d) }}" class="font-mono text-xs font-medium text-brand-600 hover:underline">{{ $d->code }}</a>
                </td>
                <td class="px-4 py-3">{{ $d->title }}</td>
                <td class="px-4 py-3 text-gray-700">{{ $d->customer?->name ?? '—' }}</td>
                <td class="px-4 py-3">
                    <x-badge :variant="$d->stage?->badgeVariant() ?? 'secondary'">{{ $d->stage?->label() }}</x-badge>
                </td>
                <td class="px-4 py-3 text-right tabular-nums">{{ number_format($d->total_amount) }} đ</td>
                <td class="px-4 py-3 text-gray-600">{{ $d->owner?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('deals.show', $d) }}" class="text-sm font-medium text-brand-600 hover:underline">Xem</a>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="7" message="Chưa có deal nào." icon="deals" />
        @endforelse
    </x-table>

    <div class="mt-4">{{ $deals->links() }}</div>
</x-layouts.app>
