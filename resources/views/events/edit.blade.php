{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<form method="POST" action="{{ route('events.update', $event) }}">
    @csrf
    @method('PUT')

    <x-input name="title" label="Tiêu đề" :value="$event->title" required />

    <x-textarea name="description" label="Mô tả" :rows="3" :value="$event->description" />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-select name="type" label="Loại" required>
            @foreach ($types as $t)
                <option value="{{ $t->value }}" @selected(old('type', $event->type?->value) === $t->value)>{{ $t->label() }}</option>
            @endforeach
        </x-select>

        <x-select name="status" label="Trạng thái" required>
            @foreach ($statuses as $s)
                <option value="{{ $s->value }}" @selected(old('status', $event->status?->value) === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </x-select>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-input name="starts_at" label="Bắt đầu" type="datetime-local" required
            :value="old('starts_at', $event->starts_at?->format('Y-m-d\TH:i'))" />
        <x-input name="ends_at" label="Kết thúc" type="datetime-local" required
            :value="old('ends_at', $event->ends_at?->format('Y-m-d\TH:i'))" />
    </div>

    <div x-data="{ online: {{ old('is_online', $event->is_online) ? 'true' : 'false' }} }" class="mb-4">
        <label class="inline-flex items-center text-sm">
            <input type="hidden" name="is_online" value="0">
            <input type="checkbox" name="is_online" value="1" x-model="online"
                class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
            <span class="ml-2">Trực tuyến (online)</span>
        </label>
        <div x-show="online" x-cloak class="mt-3">
            <x-input name="online_url" label="URL phòng họp" type="url"
                :value="old('online_url', $event->online_url)" />
        </div>
        <div x-show="!online" x-cloak class="mt-3">
            <x-input name="location" label="Địa điểm" :value="old('location', $event->location)" />
        </div>
    </div>

    <x-select name="organizer_user_id" label="Người chủ trì" required>
        @foreach ($branchUsers as $u)
            <option value="{{ $u->id }}"
                @selected((string) old('organizer_user_id', $event->organizer_user_id) === (string) $u->id)>
                {{ $u->name }} ({{ $u->email }})
            </option>
        @endforeach
    </x-select>

    <x-select name="customer_id" label="Customer liên quan" placeholder="— Không gắn Customer —">
        @foreach ($customers as $customer)
            <option value="{{ $customer->id }}"
                @selected((string) old('customer_id', $event->customer_id) === (string) $customer->id)>
                {{ $customer->name }}
            </option>
        @endforeach
    </x-select>

    <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Người tham gia</label>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-48 overflow-y-auto p-3 border border-gray-200 rounded-md">
            @forelse ($branchUsers as $u)
                <label class="inline-flex items-center text-sm">
                    <input type="checkbox" name="attendee_ids[]" value="{{ $u->id }}"
                        @checked(in_array($u->id, (array) old('attendee_ids', $attendeeIds)))
                        class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                    <span class="ml-2">{{ $u->name }}</span>
                </label>
            @empty
                <p class="text-sm text-gray-500">Không có người dùng nào trong chi nhánh.</p>
            @endforelse
        </div>
    </div>

    <x-input name="reminder_at" label="Nhắc lúc (tuỳ chọn)" type="datetime-local"
        :value="old('reminder_at', $event->reminder_at?->format('Y-m-d\TH:i'))" />

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Cập nhật</x-button>
    </div>
</form>
