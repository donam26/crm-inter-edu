{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<form method="POST" action="{{ route('deal-items.update', $item) }}">
    @csrf @method('PUT')

    <x-select name="product_id" label="Sản phẩm" placeholder="— Tự nhập —">
        @foreach($products as $p)
            <option value="{{ $p->id }}" @selected(old('product_id', $item->product_id) == $p->id)>
                {{ $p->name }} ({{ number_format($p->unit_price) }} đ)
            </option>
        @endforeach
    </x-select>

    <x-input name="name" label="Tên dòng" :value="$item->name" required />

    <x-textarea name="description" label="Mô tả" :rows="2" :value="$item->description" />

    <div class="grid grid-cols-2 gap-4">
        <x-input name="quantity" label="Số lượng" type="number" :value="$item->quantity" required />
        <x-input name="unit_price" label="Đơn giá" type="number" :value="$item->unit_price" required />
        <x-input name="discount_amount" label="Chiết khấu" type="number" :value="$item->discount_amount" />
        <x-input name="tax_rate" label="VAT %" type="number" :value="$item->tax_rate" />
    </div>

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Cập nhật</x-button>
    </div>
</form>
