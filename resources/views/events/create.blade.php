{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<form method="POST" action="{{ route('events.store') }}">
    @csrf

    <x-input name="title" label="Tiêu đề" required />

    <x-textarea name="description" label="Mô tả" :rows="3" />

    <x-select name="type" label="Loại" required>
        @foreach ($types as $t)
            <option value="{{ $t->value }}" @selected(old('type', 'meeting') === $t->value)>{{ $t->label() }}</option>
        @endforeach
    </x-select>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-input name="starts_at" label="Bắt đầu" type="datetime-local" required
            :value="old('starts_at', now()->addHour()->startOfHour()->format('Y-m-d\TH:i'))" />
        <x-input name="ends_at" label="Kết thúc" type="datetime-local" required
            :value="old('ends_at', now()->addHour()->startOfHour()->addHour()->format('Y-m-d\TH:i'))" />
    </div>

    <div x-data="{ online: {{ old('is_online') ? 'true' : 'false' }} }" class="mb-4">
        <label class="inline-flex items-center text-sm">
            <input type="hidden" name="is_online" value="0">
            <input type="checkbox" name="is_online" value="1" x-model="online"
                class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
            <span class="ml-2">Trực tuyến (online)</span>
        </label>

        <div x-show="online" x-cloak class="mt-3">
            <x-input name="online_url" label="URL phòng họp" type="url" :value="old('online_url')" />
        </div>

        <div x-show="!online" x-cloak class="mt-3">
            <x-input name="location" label="Địa điểm" :value="old('location')" />
        </div>
    </div>

    <x-select name="organizer_user_id" label="Người chủ trì" placeholder="— Chọn người —" required>
        @foreach ($branchUsers as $u)
            <option value="{{ $u->id }}"
                @selected((string) old('organizer_user_id', auth()->id()) === (string) $u->id)>
                {{ $u->name }} ({{ $u->email }})
            </option>
        @endforeach
    </x-select>

    <x-select name="customer_id" label="Customer liên quan" placeholder="— Không gắn Customer —">
        @foreach ($customers as $customer)
            <option value="{{ $customer->id }}"
                @selected((string) old('customer_id', $preselectedLeadId) === (string) $customer->id)>
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
                        @checked(in_array($u->id, (array) old('attendee_ids', [])))
                        class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                    <span class="ml-2">{{ $u->name }}</span>
                </label>
            @empty
                <p class="text-sm text-gray-500">Không có người dùng nào trong chi nhánh.</p>
            @endforelse
        </div>
        @error('attendee_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        @error('attendee_ids.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <x-input name="reminder_at" label="Nhắc lúc (tuỳ chọn)" type="datetime-local" :value="old('reminder_at')" />

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Lưu</x-button>
    </div>
</form>
