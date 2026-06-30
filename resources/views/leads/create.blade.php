{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<form method="POST" action="{{ route('leads.store') }}">
    @csrf

    <x-input name="school_name" label="Tên trường" required />

    @if (($branches ?? collect())->isNotEmpty())
        <x-select name="branch_id" label="Chi nhánh" placeholder="— Chọn chi nhánh —" required>
            @foreach ($branches as $b)
                <option value="{{ $b->id }}" @selected((string) old('branch_id') === (string) $b->id)>{{ $b->name }}</option>
            @endforeach
        </x-select>
    @endif

    <x-select name="school_level" label="Cấp học" placeholder="— Chọn cấp học —" required>
        @foreach ($levels as $l)
            <option value="{{ $l->value }}" @selected(old('school_level') === $l->value)>{{ $l->label() }}</option>
        @endforeach
    </x-select>

    <x-input name="student_size" label="Số học sinh" type="number" :value="old('student_size', 0)" />
    <x-input name="address" label="Địa chỉ" />

    <x-select name="status" label="Trạng thái">
        @foreach ($statuses as $s)
            <option value="{{ $s->value }}" @selected(old('status', 'new') === $s->value)>{{ $s->label() }}</option>
        @endforeach
    </x-select>

    <x-select name="assigned_user_id" label="Người phụ trách" placeholder="— Chưa phân công —">
        @foreach ($branchUsers as $u)
            <option value="{{ $u->id }}" @selected((string) old('assigned_user_id') === (string) $u->id)>{{ $u->name }} ({{ $u->email }})</option>
        @endforeach
    </x-select>

    <x-textarea name="note" label="Ghi chú" :rows="3" />

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Lưu</x-button>
    </div>
</form>
