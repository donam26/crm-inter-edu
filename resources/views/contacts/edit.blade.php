{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<form method="POST" action="{{ route('contacts.update', $contact) }}">
    @csrf
    @method('PUT')

    <x-input name="full_name" label="Họ tên" :value="$contact->full_name" required />
    <x-input name="position" label="Chức vụ" :value="$contact->position" />
    <x-input name="email" label="Email" type="email" :value="$contact->email" />
    <x-input name="phone" label="Số điện thoại" :value="$contact->phone" />

    <div class="mb-4">
        <label class="inline-flex items-center text-sm">
            <input type="hidden" name="is_primary" value="0">
            <input type="checkbox" name="is_primary" value="1"
                @checked(old('is_primary', $contact->is_primary))
                class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
            <span class="ml-2 text-gray-700">Đầu mối chính</span>
        </label>
        @error('is_primary')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <x-textarea name="note" label="Ghi chú" :rows="3" :value="$contact->note" />

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Cập nhật</x-button>
    </div>
</form>
