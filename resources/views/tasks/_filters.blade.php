{{--
    Partial: filter form cho trang task index.
    Dùng chung cho cả 2 view (kanban / list).

    Hidden input `view` được render lại để giữ tab đang chọn sau khi submit.

    Các biến cần có trong scope:
      - $filters (array)
      - $statuses, $priorities, $types (enum cases)
      - $branchUsers, $branches (collection)
      - $view (string: 'kanban' | 'list')
--}}
<x-card padding="p-4" class="mb-6">
    {{-- Chip lọc nhanh --}}
    @php $meId = auth()->id(); @endphp
    <div class="flex flex-wrap gap-2 mb-4">
        <a href="{{ route('tasks.index', ['view' => $view, 'assigned_user_id' => $meId]) }}"
            class="px-3 py-1 rounded-full text-xs font-medium border transition {{ (string) ($filters['assigned_user_id'] ?? '') === (string) $meId ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">Việc của tôi</a>
        <a href="{{ route('tasks.index', ['view' => $view, 'due' => 'overdue']) }}"
            class="px-3 py-1 rounded-full text-xs font-medium border transition {{ ($filters['due'] ?? '') === 'overdue' ? 'bg-red-600 text-white border-red-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">Quá hạn</a>
        <a href="{{ route('tasks.index', ['view' => $view, 'due' => 'today']) }}"
            class="px-3 py-1 rounded-full text-xs font-medium border transition {{ ($filters['due'] ?? '') === 'today' ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">Đến hạn hôm nay</a>
        <a href="{{ route('tasks.index', ['view' => $view, 'watching' => 1]) }}"
            class="px-3 py-1 rounded-full text-xs font-medium border transition {{ ! empty($filters['watching']) ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">Tôi theo dõi</a>
    </div>

    <form method="GET" action="{{ route('tasks.index') }}" class="flex flex-wrap items-end gap-3">
        {{-- Giữ tab hiện tại khi submit filter --}}
        <input type="hidden" name="view" value="{{ $view }}">

        <div class="w-44">
            <x-select name="status" label="Trạng thái" placeholder="— Tất cả —" margin="">
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </x-select>
        </div>

        <div class="w-44">
            <x-select name="priority" label="Ưu tiên" placeholder="— Tất cả —" margin="">
                @foreach ($priorities as $p)
                    <option value="{{ $p->value }}" @selected(($filters['priority'] ?? '') === $p->value)>{{ $p->label() }}</option>
                @endforeach
            </x-select>
        </div>

        <div class="w-44">
            <x-select name="type" label="Loại" placeholder="— Tất cả —" margin="">
                @foreach ($types as $t)
                    <option value="{{ $t->value }}" @selected(($filters['type'] ?? '') === $t->value)>{{ $t->label() }}</option>
                @endforeach
            </x-select>
        </div>

        <div class="w-44">
            <x-select name="due" label="Hạn" placeholder="— Tất cả —" margin="">
                <option value="overdue" @selected(($filters['due'] ?? '') === 'overdue')>Quá hạn</option>
                <option value="today" @selected(($filters['due'] ?? '') === 'today')>Hôm nay</option>
                <option value="upcoming" @selected(($filters['due'] ?? '') === 'upcoming')>24h tới</option>
                <option value="this_week" @selected(($filters['due'] ?? '') === 'this_week')>Tuần này</option>
            </x-select>
        </div>

        @hasrole('branch-manager')
            <div class="w-44">
                <x-select name="assigned_user_id" label="Người được giao" placeholder="— Tất cả —" margin="">
                    @foreach ($branchUsers as $u)
                        <option value="{{ $u->id }}" @selected((string) ($filters['assigned_user_id'] ?? '') === (string) $u->id)>{{ $u->name }}</option>
                    @endforeach
                </x-select>
            </div>
        @endhasrole

        @hasrole('super-admin')
            <div class="w-44">
                <x-select name="branch_id" label="Chi nhánh" placeholder="— Tất cả —" margin="">
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $b->id)>{{ $b->name }}</option>
                    @endforeach
                </x-select>
            </div>
        @endhasrole

        <div class="w-56">
            <x-input name="q" label="Tìm kiếm" :value="$filters['q'] ?? ''" placeholder="Tiêu đề / nội dung" margin="" />
        </div>

        <div class="flex gap-2">
            <x-button type="submit" variant="primary"><x-icon name="search" class="h-4 w-4" /> Lọc</x-button>
            <a href="{{ route('tasks.index', ['view' => $view]) }}">
                <x-button type="button" variant="secondary">Xoá lọc</x-button>
            </a>
        </div>
    </form>
</x-card>
