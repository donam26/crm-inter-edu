{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<form method="POST" action="{{ route('users.update', $user) }}"
    x-data="{ branch: '{{ old('branch_id', $user->branch_id ?? '') }}' }">
    @csrf
    @method('PUT')

    <x-input name="name" label="Họ tên" :value="$user->name" required />
    <x-input name="email" label="Email" type="email" :value="$user->email" required />
    <x-input name="password" label="Mật khẩu mới (để trống để giữ)" type="password" />
    <x-input name="password_confirmation" label="Xác nhận mật khẩu" type="password" />

    @if ($isSuperAdmin)
        <x-select name="branch_id" label="Chi nhánh" placeholder="— Không thuộc chi nhánh (Super Admin) —" x-model="branch">
            @foreach ($branches as $b)
                <option value="{{ $b->id }}" @selected((string) old('branch_id', $user->branch_id) === (string) $b->id)>{{ $b->name }}</option>
            @endforeach
        </x-select>
    @endif

    @include('users._roles', ['roles' => $roles, 'isSuperAdmin' => $isSuperAdmin, 'assignedRoles' => $assignedRoles])

    <div class="flex items-center justify-end gap-3 pt-4 mt-4 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Cập nhật</x-button>
    </div>
</form>
