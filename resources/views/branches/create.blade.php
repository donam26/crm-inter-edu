{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<form method="POST" action="{{ route('branches.store') }}">
    @csrf

    <x-input name="name" label="Tên chi nhánh" required />
    <x-input name="code" label="Mã chi nhánh" required />
    <x-input name="address" label="Địa chỉ" />
    <x-input name="phone" label="Số điện thoại" />

    <div class="mb-4">
        <label class="inline-flex items-center text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }} class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
            <span class="ml-2 text-gray-700">Đang hoạt động</span>
        </label>
    </div>

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Lưu</x-button>
    </div>
</form>
