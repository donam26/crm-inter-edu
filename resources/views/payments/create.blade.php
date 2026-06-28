{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<p class="text-sm text-gray-500 mb-4">
    Hoá đơn: <span class="font-mono">{{ $invoice->code }}</span> ·
    Tổng: <span class="font-semibold">{{ number_format($invoice->total_amount) }} đ</span> ·
    Đã thu: <span class="text-green-700">{{ number_format($invoice->paid_amount) }} đ</span> ·
    Còn lại: <span class="text-yellow-700 font-semibold">{{ number_format($invoice->balance()) }} đ</span>
</p>

<form method="POST" action="{{ route('invoices.payments.store', $invoice) }}">
    @csrf

    <x-input name="amount" label="Số tiền" type="number" :value="$invoice->balance()" required />

    <x-select name="method" label="Phương thức" required>
        @foreach($methods as $m)
            <option value="{{ $m->value }}" @selected(old('method') === $m->value)>{{ $m->label() }}</option>
        @endforeach
    </x-select>

    <x-input name="paid_at" label="Ngày thu" type="date" :value="now()->toDateString()" required />
    <x-input name="reference_no" label="Mã giao dịch (tùy chọn)" />

    <x-textarea name="note" label="Ghi chú" :rows="2" />

    <div class="mb-4">
        <label class="inline-flex items-center text-sm">
            <input type="hidden" name="confirm" value="0">
            <input type="checkbox" name="confirm" value="1" {{ old('confirm', '1') ? 'checked' : '' }} class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
            <span class="ml-2 text-gray-700">Xác nhận ngay (cập nhật ngay vào tổng đã thu)</span>
        </label>
    </div>

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Lưu</x-button>
    </div>
</form>
