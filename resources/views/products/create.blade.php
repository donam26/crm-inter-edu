{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<form method="POST" action="{{ route('products.store') }}">
    @csrf

    @if($branches->isNotEmpty())
        <x-select name="branch_id" label="Chi nhánh" placeholder="— Chọn chi nhánh —" required>
            @foreach($branches as $b)
                <option value="{{ $b->id }}" @selected(old('branch_id') == $b->id)>{{ $b->name }}</option>
            @endforeach
        </x-select>
    @endif

    <x-input name="code" label="Mã sản phẩm" required />
    <x-input name="name" label="Tên sản phẩm" required />

    <x-textarea name="description" label="Mô tả" :rows="3" />

    <x-input name="unit_price" label="Đơn giá (VND)" type="number" required value="0" />

    <div class="mb-4">
        <label class="inline-flex items-center text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }} class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
            <span class="ml-2 text-gray-700">Đang bán</span>
        </label>
    </div>

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Lưu</x-button>
    </div>
</form>
