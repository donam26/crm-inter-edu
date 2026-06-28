{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<form method="POST" action="{{ route('products.update', $product) }}">
    @csrf
    @method('PUT')

    <x-input name="code" label="Mã sản phẩm" :value="$product->code" required />
    <x-input name="name" label="Tên sản phẩm" :value="$product->name" required />

    <x-textarea name="description" label="Mô tả" :rows="3" :value="$product->description" />

    <x-input name="unit_price" label="Đơn giá (VND)" type="number" :value="$product->unit_price" required />

    <div class="mb-4">
        <label class="inline-flex items-center text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $product->is_active) ? 'checked' : '' }} class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
            <span class="ml-2 text-gray-700">Đang bán</span>
        </label>
    </div>

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Cập nhật</x-button>
    </div>
</form>
