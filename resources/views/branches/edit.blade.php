{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<form method="POST" action="{{ route('branches.update', $branch) }}">
    @csrf
    @method('PUT')

    <x-input name="name" label="Tên chi nhánh" :value="$branch->name" required />
    <x-input name="code" label="Mã chi nhánh" :value="$branch->code" required />
    <x-input name="address" label="Địa chỉ" :value="$branch->address" />
    <x-input name="phone" label="Số điện thoại" :value="$branch->phone" />

    <div class="mb-4">
        <label class="inline-flex items-center text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $branch->is_active) ? 'checked' : '' }} class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
            <span class="ml-2 text-gray-700">Đang hoạt động</span>
        </label>
    </div>

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Cập nhật</x-button>
    </div>
</form>
