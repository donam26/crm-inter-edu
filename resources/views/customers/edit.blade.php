{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<form method="POST" action="{{ route('customers.update', $customer) }}">
    @csrf
    @method('PUT')

    <x-input name="name" label="Tên khách hàng" :value="$customer->name" required />

    <x-input name="phone" label="Điện thoại" :value="old('phone', $customer->phone)" />
    <x-input name="email" label="Email" type="email" :value="old('email', $customer->email)" />
    <x-input name="address" label="Địa chỉ" :value="$customer->address" />

    <x-select name="status" label="Trạng thái" required>
        @foreach ($statuses as $s)
            <option value="{{ $s->value }}" @selected(old('status', $customer->status?->value) === $s->value)>{{ $s->label() }}</option>
        @endforeach
    </x-select>

    <x-select name="assigned_user_id" label="Người phụ trách" placeholder="— Chưa phân công —">
        @foreach ($branchUsers as $u)
            <option value="{{ $u->id }}" @selected((string) old('assigned_user_id', $customer->assigned_user_id) === (string) $u->id)>{{ $u->name }} ({{ $u->email }})</option>
        @endforeach
    </x-select>

    <x-textarea name="note" label="Ghi chú" :rows="3" :value="$customer->note" />

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Cập nhật</x-button>
    </div>
</form>
