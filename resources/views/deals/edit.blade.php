{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<form method="POST" action="{{ route('deals.update', $deal) }}">
    @csrf @method('PUT')

    <x-input name="title" label="Tiêu đề" :value="$deal->title" required />

    <x-select name="owner_user_id" label="Người phụ trách" placeholder="— Không gán —">
        @foreach($branchUsers as $u)
            <option value="{{ $u->id }}" @selected(old('owner_user_id', $deal->owner_user_id) == $u->id)>{{ $u->name }}</option>
        @endforeach
    </x-select>

    <x-input name="expected_close_date" label="Ngày dự kiến chốt" type="date"
        :value="$deal->expected_close_date?->format('Y-m-d')" />

    <x-textarea name="note" label="Ghi chú" :rows="3" :value="$deal->note" />

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Cập nhật</x-button>
    </div>
</form>
