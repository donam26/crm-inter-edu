{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
@php $isSuperAdmin = ($branches ?? collect())->isNotEmpty(); @endphp

<form method="POST" action="{{ route('leads.store') }}"
    @if ($isSuperAdmin)
        x-data="{
            branchId: @js((string) old('branch_id', '')),
            assignedUserId: @js((string) old('assigned_user_id', '')),
            users: @js($branchUsers->map(fn ($u) => [
                'id' => (string) $u->id,
                'branchId' => (string) $u->branch_id,
                'label' => $u->name.' ('.$u->email.')',
            ])->values()),
            get assignable() {
                return this.branchId
                    ? this.users.filter((u) => u.branchId === this.branchId)
                    : [];
            },
        }"
        x-init="$watch('branchId', () => {
            if (! assignable.some((u) => u.id === assignedUserId)) assignedUserId = '';
        })"
    @endif
>
    @csrf

    <x-input name="school_name" label="Tên trường" required />

    @if ($isSuperAdmin)
        <x-select name="branch_id" label="Chi nhánh" placeholder="— Chọn chi nhánh —" required x-model="branchId">
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

    @if ($isSuperAdmin)
        <x-select name="assigned_user_id" label="Người phụ trách" placeholder="— Chưa phân công —"
            x-model="assignedUserId" x-bind:disabled="! branchId">
            <template x-for="u in assignable" :key="u.id">
                <option :value="u.id" x-text="u.label"></option>
            </template>
        </x-select>
        <p x-show="! branchId" x-cloak class="-mt-3 mb-4 text-xs text-gray-500">
            Chọn chi nhánh trước để gán người phụ trách.
        </p>
    @else
        <x-select name="assigned_user_id" label="Người phụ trách" placeholder="— Chưa phân công —">
            @foreach ($branchUsers as $u)
                <option value="{{ $u->id }}" @selected((string) old('assigned_user_id') === (string) $u->id)>{{ $u->name }} ({{ $u->email }})</option>
            @endforeach
        </x-select>
    @endif

    <x-textarea name="note" label="Ghi chú" :rows="3" />

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Lưu</x-button>
    </div>
</form>
