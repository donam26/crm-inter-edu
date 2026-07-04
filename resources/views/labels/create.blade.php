{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
@php
    $colorLabels = [
        'secondary' => 'Xám', 'primary' => 'Xanh (thương hiệu)',
        'success' => 'Xanh lá', 'warning' => 'Vàng', 'danger' => 'Đỏ',
    ];
@endphp
<form method="POST" action="{{ route('labels.store') }}">
    @csrf

    <x-input name="name" label="Tên nhãn" required :value="old('name')" />

    <x-select name="color" label="Màu" required>
        @foreach ($colors as $c)
            <option value="{{ $c }}" @selected(old('color', 'secondary') === $c)>{{ $colorLabels[$c] ?? $c }}</option>
        @endforeach
    </x-select>

    @if ($branches->isNotEmpty())
        <x-select name="branch_id" label="Chi nhánh" placeholder="— Chọn chi nhánh —" required>
            @foreach ($branches as $b)
                <option value="{{ $b->id }}" @selected((string) old('branch_id') === (string) $b->id)>{{ $b->name }}</option>
            @endforeach
        </x-select>
    @endif

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Lưu</x-button>
    </div>
</form>
