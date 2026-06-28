{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<form method="POST" action="{{ route('deals.invoices.store', $deal) }}">
    @csrf

    <x-input name="due_at" label="Hạn thanh toán" type="date" />

    <x-textarea name="note" label="Ghi chú" :rows="3" />

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Lưu</x-button>
    </div>
</form>
