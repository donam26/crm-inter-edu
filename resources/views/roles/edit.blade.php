{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<form method="POST" action="{{ route('roles.update', $role) }}">
    @csrf
    @method('PUT')

    <x-input name="name" label="Tên vai trò" :value="$role->name" required />
    <p class="-mt-2 mb-3 text-xs text-gray-500">Chọn các quyền cấp cho vai trò này.</p>

    @include('roles._permissions', ['groups' => $groups, 'assigned' => $assigned])

    <div class="flex items-center justify-end gap-3 pt-4 mt-4 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Cập nhật</x-button>
    </div>
</form>
