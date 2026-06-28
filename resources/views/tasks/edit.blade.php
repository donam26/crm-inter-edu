{{-- Modal content (nạp qua AJAX). Không bọc layout. --}}
<form method="POST" action="{{ route('tasks.update', $task) }}">
    @csrf
    @method('PUT')

    <x-input name="title" label="Tiêu đề" :value="$task->title" required />

    <x-textarea name="description" label="Mô tả" :rows="4" :value="$task->description" />

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-select name="type" label="Loại" required>
            @foreach ($types as $t)
                <option value="{{ $t->value }}" @selected(old('type', $task->type?->value) === $t->value)>{{ $t->label() }}</option>
            @endforeach
        </x-select>

        <x-select name="priority" label="Ưu tiên" required>
            @foreach ($priorities as $p)
                <option value="{{ $p->value }}" @selected(old('priority', $task->priority?->value) === $p->value)>{{ $p->label() }}</option>
            @endforeach
        </x-select>

        <x-select name="status" label="Trạng thái" required>
            @foreach ($statuses as $s)
                <option value="{{ $s->value }}" @selected(old('status', $task->status?->value) === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </x-select>
    </div>

    <x-input name="due_at" label="Hạn chót" type="datetime-local" required
        :value="old('due_at', $task->due_at?->format('Y-m-d\TH:i'))" />

    <x-select name="assigned_user_id" label="Người được giao" required>
        @foreach ($branchUsers as $u)
            <option value="{{ $u->id }}"
                @selected((string) old('assigned_user_id', $task->assigned_user_id) === (string) $u->id)>
                {{ $u->name }} ({{ $u->email }})
            </option>
        @endforeach
    </x-select>

    <x-select name="lead_id" label="Lead liên quan" placeholder="— Không gắn Lead —">
        @foreach ($leads as $lead)
            <option value="{{ $lead->id }}"
                @selected((string) old('lead_id', $task->lead_id) === (string) $lead->id)>
                {{ $lead->school_name }}
            </option>
        @endforeach
    </x-select>

    <div x-data="{ enabled: {{ old('reminder_enabled', $task->reminder_enabled) ? 'true' : 'false' }} }" class="mb-4">
        <label class="inline-flex items-center text-sm">
            <input type="hidden" name="reminder_enabled" value="0">
            <input type="checkbox" name="reminder_enabled" value="1" x-model="enabled"
                class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
            <span class="ml-2">Bật nhắc nhở</span>
        </label>

        <div x-show="enabled" x-cloak class="mt-3">
            <x-input name="remind_at" label="Thời điểm nhắc" type="datetime-local"
                :value="old('remind_at', $task->remind_at?->format('Y-m-d\TH:i'))" />
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 pt-4 mt-2 border-t border-gray-100">
        <x-button type="button" variant="secondary" data-modal-close>Hủy</x-button>
        <x-button type="submit" variant="primary">Cập nhật</x-button>
    </div>
</form>
