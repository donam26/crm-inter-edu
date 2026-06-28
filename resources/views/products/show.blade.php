<x-layouts.app title="Chi tiết sản phẩm" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Sản phẩm', 'url' => route('products.index')],
    ['label' => $product->name],
]">
    <div class="max-w-2xl">
        <x-page-header :title="$product->name">
            @can('update', $product)
                <x-button variant="secondary" data-modal-form="{{ route('products.edit', $product) }}" data-modal-title="Sửa sản phẩm">Sửa</x-button>
            @endcan
            @can('delete', $product)
                <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('Xoá sản phẩm?');" class="inline">
                    @csrf @method('DELETE')
                    <x-button type="submit" variant="danger">Xoá</x-button>
                </form>
            @endcan
        </x-page-header>

        <x-card>
            <dl class="space-y-3 text-sm">
                <div class="flex"><span class="w-40 text-gray-500">Mã:</span><span class="font-mono">{{ $product->code }}</span></div>
                <div class="flex"><span class="w-40 text-gray-500">Tên:</span><span>{{ $product->name }}</span></div>
                <div class="flex"><span class="w-40 text-gray-500">Mô tả:</span><span class="whitespace-pre-line">{{ $product->description ?? '—' }}</span></div>
                <div class="flex"><span class="w-40 text-gray-500">Đơn giá:</span><span class="font-semibold">{{ number_format($product->unit_price) }} đ</span></div>
                <div class="flex"><span class="w-40 text-gray-500">Chi nhánh:</span><span>{{ $product->branch?->name }}</span></div>
                <div class="flex"><span class="w-40 text-gray-500">Trạng thái:</span>
                    <x-badge :variant="$product->is_active ? 'success' : 'secondary'">{{ $product->is_active ? 'Đang bán' : 'Ngừng' }}</x-badge>
                </div>
            </dl>
        </x-card>

        <div class="mt-6">
            <a href="{{ route('products.index') }}" class="inline-flex items-center gap-1 text-sm text-brand-600 hover:underline">
                <x-icon name="arrow-left" class="h-4 w-4" /> Quay lại danh sách
            </a>
        </div>
    </div>
</x-layouts.app>
