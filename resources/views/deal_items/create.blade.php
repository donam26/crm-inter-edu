{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<form method="POST" action="{{ route('deals.items.store', $deal) }}" x-data="{
    productId: '',
    unitPrice: 0,
    quantity: 1,
    discount: 0,
    taxRate: {{ old('tax_rate', 8) }},
    products: @js($products->mapWithKeys(fn ($p) => [$p->id => ['name' => $p->name, 'unit_price' => $p->unit_price]])->toArray()),
    onProductChange() {
        if (this.productId && this.products[this.productId]) {
            const p = this.products[this.productId];
            if (!this.$refs.name.value) this.$refs.name.value = p.name;
            if (!this.unitPrice || this.unitPrice == 0) this.unitPrice = p.unit_price;
        }
    },
    get lineSubtotal() {
        return Math.max(0, (this.quantity * this.unitPrice) - this.discount);
    },
    get lineTax() {
        return Math.round(this.lineSubtotal * this.taxRate / 100);
    },
    get lineTotal() {
        return this.lineSubtotal + this.lineTax;
    },
    fmt(v) { return new Intl.NumberFormat('vi-VN').format(v) + ' đ'; }
}">
    @csrf

    <x-select name="product_id" label="Sản phẩm (tùy chọn)" placeholder="— Tự nhập —" x-model="productId" @change="onProductChange()">
        @foreach($products as $p)
            <option value="{{ $p->id }}">{{ $p->name }} ({{ number_format($p->unit_price) }} đ)</option>
        @endforeach
    </x-select>

    <x-input name="name" label="Tên dòng" x-ref="name" />

    <x-textarea name="description" label="Mô tả" :rows="2" />

    <div class="grid grid-cols-2 gap-4">
        <x-input name="quantity" label="Số lượng" type="number" min="1" x-model.number="quantity" required />
        <x-input name="unit_price" label="Đơn giá" type="number" min="0" x-model.number="unitPrice" required />
        <x-input name="discount_amount" label="Chiết khấu (VND)" type="number" min="0" x-model.number="discount" />
        <x-input name="tax_rate" label="VAT %" type="number" min="0" max="100" x-model.number="taxRate" />
    </div>

    <div class="mt-6 p-4 bg-gray-50 rounded-md text-sm">
        <div class="flex justify-between"><span>Tạm tính:</span><span class="tabular-nums" x-text="fmt(lineSubtotal)"></span></div>
        <div class="flex justify-between"><span>VAT:</span><span class="tabular-nums" x-text="fmt(lineTax)"></span></div>
        <div class="flex justify-between font-semibold mt-2 pt-2 border-t border-gray-200">
            <span>Tổng dòng:</span><span class="tabular-nums" x-text="fmt(lineTotal)"></span>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Lưu</x-button>
    </div>
</form>
