<x-layouts.app title="Sản phẩm" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Sản phẩm'],
]">
    <x-page-header title="Sản phẩm">
        @can('create', App\Models\Product::class)
            <x-button variant="primary" data-modal-form="{{ route('products.create') }}" data-modal-title="Thêm sản phẩm">
                <x-icon name="plus" class="h-4 w-4" /> Thêm sản phẩm
            </x-button>
        @endcan
    </x-page-header>

    {{-- Filters --}}
    <x-card padding="p-4" class="mb-4">
        <form method="GET" action="{{ route('products.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="w-64">
                <x-input name="q" label="Tìm kiếm" :value="request('q')" placeholder="Tên hoặc mã" margin="" />
            </div>
            <div class="w-44">
                <x-select name="is_active" label="Trạng thái" placeholder="Tất cả" margin="">
                    <option value="1" @selected(request('is_active') === '1')>Đang bán</option>
                    <option value="0" @selected(request('is_active') === '0')>Ngừng bán</option>
                </x-select>
            </div>
            @if($branches->isNotEmpty())
                <div class="w-44">
                    <x-select name="branch_id" label="Chi nhánh" placeholder="Tất cả" margin="">
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" @selected(request('branch_id') == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </x-select>
                </div>
            @endif
            <x-button type="submit" variant="secondary"><x-icon name="search" class="h-4 w-4" /> Lọc</x-button>
        </form>
    </x-card>

    <x-table :headers="['Mã', 'Tên', 'Đơn giá', 'Trạng thái', '']">
        @forelse ($products as $p)
            <tr>
                <td class="px-4 py-3 font-mono text-xs">{{ $p->code }}</td>
                <td class="px-4 py-3">
                    <a href="{{ route('products.show', $p) }}" class="font-medium text-gray-900 hover:text-brand-700">{{ $p->name }}</a>
                </td>
                <td class="px-4 py-3 text-right tabular-nums">{{ number_format($p->unit_price) }} đ</td>
                <td class="px-4 py-3">
                    <x-badge :variant="$p->is_active ? 'success' : 'secondary'">
                        {{ $p->is_active ? 'Đang bán' : 'Ngừng' }}
                    </x-badge>
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('products.show', $p) }}" class="text-sm font-medium text-brand-600 hover:underline">Xem</a>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="5" message="Chưa có sản phẩm." icon="product" />
        @endforelse
    </x-table>

    <div class="mt-4">{{ $products->links() }}</div>
</x-layouts.app>
