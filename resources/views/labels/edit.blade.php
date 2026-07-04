{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
@php
    $colorLabels = [
        'secondary' => 'Xám', 'primary' => 'Xanh (thương hiệu)',
        'success' => 'Xanh lá', 'warning' => 'Vàng', 'danger' => 'Đỏ',
    ];
@endphp
<form method="POST" action="{{ route('labels.update', $label) }}">
    @csrf
    @method('PUT')

    <x-input name="name" label="Tên nhãn" required :value="old('name', $label->name)" />

    <x-select name="color" label="Màu" required>
        @foreach ($colors as $c)
            <option value="{{ $c }}" @selected(old('color', $label->color) === $c)>{{ $colorLabels[$c] ?? $c }}</option>
        @endforeach
    </x-select>

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Cập nhật</x-button>
    </div>
</form>
