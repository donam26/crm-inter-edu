{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
@if ($payment->confirmed_at)
    <x-alert type="info">Thanh toán đã xác nhận. Chỉ có thể chỉnh sửa ghi chú và mã giao dịch.</x-alert>
@endif

<form method="POST" action="{{ route('payments.update', $payment) }}">
    @csrf
    @method('PUT')

    @if (! $payment->confirmed_at)
        <x-input name="amount" label="Số tiền" type="number" :value="$payment->amount" />

        <x-select name="method" label="Phương thức">
            @foreach($methods as $m)
                <option value="{{ $m->value }}" @selected($payment->method?->value === $m->value)>{{ $m->label() }}</option>
            @endforeach
        </x-select>

        <x-input name="paid_at" label="Ngày thu" type="date" :value="$payment->paid_at?->format('Y-m-d')" />
    @endif

    <x-input name="reference_no" label="Mã giao dịch" :value="$payment->reference_no" />

    <x-textarea name="note" label="Ghi chú" :rows="2" :value="$payment->note" />

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Cập nhật</x-button>
    </div>
</form>
