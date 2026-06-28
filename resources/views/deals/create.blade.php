{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<form method="POST" action="{{ route('deals.store') }}">
    @csrf

    <x-select name="lead_id" label="Lead" placeholder="— Chọn lead —" required>
        @foreach($leads as $l)
            <option value="{{ $l->id }}" @selected(old('lead_id', $preselectedLeadId) == $l->id)>
                {{ $l->school_name }}
            </option>
        @endforeach
    </x-select>
    <p class="-mt-3 mb-4 text-xs text-gray-500">Chỉ hiện những lead chưa có deal (1 lead = 1 deal).</p>

    <x-input name="title" label="Tiêu đề (tùy chọn — mặc định lấy theo tên trường)" />

    @if($branchUsers->isNotEmpty())
        <x-select name="owner_user_id" label="Người phụ trách" placeholder="— Mặc định lấy theo lead.assigned_user_id —">
            @foreach($branchUsers as $u)
                <option value="{{ $u->id }}" @selected(old('owner_user_id') == $u->id)>{{ $u->name }}</option>
            @endforeach
        </x-select>
    @endif

    <x-input name="expected_close_date" label="Ngày dự kiến chốt" type="date" />

    <x-textarea name="note" label="Ghi chú" :rows="3" />

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Lưu</x-button>
    </div>
</form>
