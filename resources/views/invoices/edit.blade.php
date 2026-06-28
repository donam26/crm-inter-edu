{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<form method="POST" action="{{ route('invoices.update', $invoice) }}">
    @csrf
    @method('PUT')

    <x-input name="due_at" label="Hạn thanh toán" type="date" :value="$invoice->due_at?->format('Y-m-d')" />

    <x-textarea name="note" label="Ghi chú" :rows="3" :value="$invoice->note" />

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Cập nhật</x-button>
    </div>
</form>
