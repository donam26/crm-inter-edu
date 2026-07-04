{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<form method="POST" action="{{ route('tasks.store') }}">
    @csrf

    <x-input name="title" label="Tiêu đề" required />

    <x-textarea name="description" label="Mô tả" :rows="4" />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-select name="type" label="Loại" required>
            @foreach ($types as $t)
                <option value="{{ $t->value }}" @selected(old('type') === $t->value)>{{ $t->label() }}</option>
            @endforeach
        </x-select>

        <x-select name="priority" label="Ưu tiên" required>
            @foreach ($priorities as $p)
                <option value="{{ $p->value }}" @selected(old('priority', 'medium') === $p->value)>{{ $p->label() }}</option>
            @endforeach
        </x-select>
    </div>

    <x-input name="start_at" label="Ngày bắt đầu (tuỳ chọn)" type="datetime-local"
        :value="old('start_at')" />

    <x-input name="due_at" label="Hạn chót" type="datetime-local" required
        :value="old('due_at', now()->addDay()->format('Y-m-d\TH:i'))" />

    <x-select name="assigned_user_id" label="Người được giao" placeholder="— Chọn người —" required>
        @foreach ($branchUsers as $u)
            <option value="{{ $u->id }}" @selected((string) old('assigned_user_id', auth()->id()) === (string) $u->id)>
                {{ $u->name }} ({{ $u->email }})
            </option>
        @endforeach
    </x-select>

    <x-select name="lead_id" label="Lead liên quan" placeholder="— Không gắn Lead —">
        @foreach ($leads as $lead)
            <option value="{{ $lead->id }}"
                @selected((string) old('lead_id', $preselectedLeadId) === (string) $lead->id)>
                {{ $lead->school_name }}
            </option>
        @endforeach
    </x-select>

    <div x-data="{ enabled: {{ old('reminder_enabled') ? 'true' : 'false' }} }" class="mb-4">
        <label class="inline-flex items-center text-sm">
            <input type="hidden" name="reminder_enabled" value="0">
            <input type="checkbox" name="reminder_enabled" value="1" x-model="enabled"
                class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
            <span class="ml-2">Bật nhắc nhở</span>
        </label>

        <div x-show="enabled" x-cloak class="mt-3">
            <x-input name="remind_at" label="Thời điểm nhắc" type="datetime-local"
                :value="old('remind_at')" />
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Lưu</x-button>
    </div>
</form>
