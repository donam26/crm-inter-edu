{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<form method="POST" action="{{ route('leads.update', $lead) }}">
    @csrf
    @method('PUT')

    <x-input name="school_name" label="Tên trường" :value="$lead->school_name" required />

    <x-select name="school_level" label="Cấp học" required>
        @foreach ($levels as $l)
            <option value="{{ $l->value }}" @selected(old('school_level', $lead->school_level?->value) === $l->value)>{{ $l->label() }}</option>
        @endforeach
    </x-select>

    <x-input name="student_size" label="Số học sinh" type="number" :value="$lead->student_size" />
    <x-input name="address" label="Địa chỉ" :value="$lead->address" />

    <x-select name="status" label="Trạng thái" required>
        @foreach ($statuses as $s)
            <option value="{{ $s->value }}" @selected(old('status', $lead->status?->value) === $s->value)>{{ $s->label() }}</option>
        @endforeach
    </x-select>

    <x-select name="assigned_user_id" label="Người phụ trách" placeholder="— Chưa phân công —">
        @foreach ($branchUsers as $u)
            <option value="{{ $u->id }}" @selected((string) old('assigned_user_id', $lead->assigned_user_id) === (string) $u->id)>{{ $u->name }} ({{ $u->email }})</option>
        @endforeach
    </x-select>

    <x-textarea name="note" label="Ghi chú" :rows="3" :value="$lead->note" />

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Cập nhật</x-button>
    </div>
</form>
