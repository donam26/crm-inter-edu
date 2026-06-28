{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<form method="POST" action="{{ route('activities.update', $activity) }}">
    @csrf
    @method('PUT')

    <x-select name="type" label="Loại" required>
        @foreach ($types as $t)
            <option value="{{ $t->value }}"
                @selected(old('type', $activity->type?->value) === $t->value)>{{ $t->label() }}</option>
        @endforeach
    </x-select>

    <x-input name="subject" label="Tiêu đề" :value="$activity->subject" required />

    <x-textarea name="content" label="Nội dung" :rows="4" :value="$activity->content" />

    <x-input name="happened_at" label="Thời gian" type="datetime-local"
        :value="old('happened_at', $activity->happened_at?->format('Y-m-d\TH:i'))" required />

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Cập nhật</x-button>
    </div>
</form>
