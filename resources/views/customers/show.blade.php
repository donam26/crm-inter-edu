<x-layouts.app title="Chi tiết khách hàng" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Khách hàng', 'url' => route('customers.index')],
    ['label' => $customer->name],
]">
    <div class="max-w-3xl">
        <x-page-header :title="$customer->name">
            @can('update', $customer)
                <x-button variant="secondary" data-modal-form="{{ route('customers.edit', $customer) }}" data-modal-title="Sửa khách hàng">Sửa</x-button>
            @endcan
            @can('delete', $customer)
                <form method="POST" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('Xóa khách hàng này?');" class="inline">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger">Xóa</x-button>
                </form>
            @endcan
        </x-page-header>

        {{-- Thông tin khách hàng --}}
        <x-card>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <dt class="text-gray-500">Tên khách hàng</dt>
                    <dd class="font-medium text-gray-900 mt-0.5">{{ $customer->name }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Điện thoại</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $customer->phone ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Email</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $customer->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Trạng thái</dt>
                    <dd class="mt-0.5"><x-badge variant="primary" dot>{{ $customer->status?->label() }}</x-badge></dd>
                </div>
                <div>
                    <dt class="text-gray-500">Chi nhánh</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $customer->branch?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Người phụ trách</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $customer->assignedUser?->name ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-gray-500">Địa chỉ</dt>
                    <dd class="text-gray-900 mt-0.5">{{ $customer->address ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-gray-500">Ghi chú</dt>
                    <dd class="text-gray-900 mt-0.5 whitespace-pre-line">{{ $customer->note ?? '—' }}</dd>
                </div>
            </dl>
        </x-card>

        {{-- Gán người phụ trách --}}
        @can('assign', $customer)
            <x-card title="Gán người phụ trách" class="mt-6">
                <form method="POST" action="{{ route('customers.assign', $customer) }}" class="flex items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <x-select name="assigned_user_id" placeholder="— Bỏ phân công —" margin="">
                            @foreach ($branchUsers as $u)
                                <option value="{{ $u->id }}" @selected($customer->assigned_user_id === $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </x-select>
                    </div>
                    <x-button type="submit" variant="primary">Cập nhật</x-button>
                </form>
            </x-card>
        @endcan

        {{-- Hoạt động --}}
        <x-card title="Hoạt động" class="mt-6">
            @can('create', [\App\Models\Activity::class, $customer])
                <x-slot:actions>
                    <x-button variant="secondary" size="sm" data-modal-form="{{ route('customers.activities.create', $customer) }}" data-modal-title="Thêm hoạt động">
                        <x-icon name="plus" class="h-4 w-4" /> Thêm hoạt động
                    </x-button>
                </x-slot:actions>
            @endcan
            @forelse ($customer->activities()->orderByDesc('happened_at')->with('user')->get() as $a)
                <div class="border-b border-gray-100 py-3 last:border-0 last:pb-0">
                    <div class="flex items-center gap-2 text-sm">
                        <x-badge variant="primary">{{ $a->type?->label() }}</x-badge>
                        <a href="{{ route('activities.show', $a) }}"
                            class="font-medium text-brand-600 hover:underline">{{ $a->subject }}</a>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ $a->user?->name ?? '—' }} · {{ $a->happened_at?->format('d/m/Y H:i') }}
                    </div>
                    @if ($a->content)
                        <p class="text-sm text-gray-700 mt-2 whitespace-pre-line line-clamp-3">{{ $a->content }}</p>
                    @endif
                </div>
            @empty
                <x-empty-state message="Chưa có hoạt động." />
            @endforelse
        </x-card>

        {{-- Công việc --}}
        @if (class_exists(\App\Models\Task::class))
            <x-card title="Công việc" class="mt-6">
                @can('create', \App\Models\Task::class)
                    <x-slot:actions>
                        <x-button variant="secondary" size="sm" data-modal-form="{{ route('tasks.create', ['customer_id' => $customer->id]) }}" data-modal-title="Tạo task">
                            <x-icon name="plus" class="h-4 w-4" /> Tạo task
                        </x-button>
                    </x-slot:actions>
                @endcan
                @php
                    $leadTasks = $customer->tasks()
                        ->with('assignee')
                        ->orderByRaw("CASE WHEN status IN ('pending','in_progress') THEN 0 ELSE 1 END")
                        ->orderBy('due_at')
                        ->get();
                @endphp
                @forelse ($leadTasks as $task)
                    <div class="flex items-center justify-between border-b border-gray-100 py-2 last:border-0 text-sm">
                        <div class="min-w-0">
                            <a href="{{ route('tasks.show', $task) }}"
                                class="font-medium text-brand-600 hover:underline">{{ $task->title }}</a>
                            <div class="text-xs text-gray-500 mt-0.5 flex items-center gap-2 flex-wrap">
                                <x-badge :variant="$task->status?->badgeVariant() ?? 'secondary'">{{ $task->status?->label() }}</x-badge>
                                <x-badge :variant="$task->priority?->badgeVariant() ?? 'secondary'">{{ $task->priority?->label() }}</x-badge>
                                <span>{{ $task->due_at?->format('d/m/Y H:i') }}</span>
                                @if ($task->is_overdue)
                                    <x-badge variant="danger">Quá hạn</x-badge>
                                @endif
                                <span>· {{ $task->assignee?->name ?? '—' }}</span>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            @can('complete', $task)
                                @if ($task->status?->isOpen())
                                    <form method="POST" action="{{ route('tasks.complete', $task) }}" class="inline">
                                        @csrf
                                        <button class="text-xs font-medium text-green-600 hover:text-green-800">Hoàn thành</button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    </div>
                @empty
                    <x-empty-state message="Chưa có công việc." icon="tasks" />
                @endforelse
            </x-card>
        @endif

        {{-- Lịch hẹn --}}
        @if (class_exists(\App\Models\Event::class))
            <x-card title="Lịch hẹn" class="mt-6">
                @can('create', \App\Models\Event::class)
                    <x-slot:actions>
                        <x-button variant="secondary" size="sm" data-modal-form="{{ route('events.create', ['customer_id' => $customer->id]) }}" data-modal-title="Tạo lịch hẹn">
                            <x-icon name="plus" class="h-4 w-4" /> Tạo lịch
                        </x-button>
                    </x-slot:actions>
                @endcan
                @php
                    $leadEvents = $customer->events()->with('organizer')->orderBy('starts_at')->get();
                @endphp
                @forelse ($leadEvents as $ev)
                    <div class="flex items-center justify-between border-b border-gray-100 py-2 last:border-0 text-sm">
                        <div class="min-w-0">
                            <a href="{{ route('events.show', $ev) }}"
                                class="font-medium text-brand-600 hover:underline">{{ $ev->title }}</a>
                            <div class="text-xs text-gray-500 mt-0.5 flex items-center gap-2 flex-wrap">
                                <x-badge :variant="$ev->status?->badgeVariant() ?? 'secondary'">{{ $ev->status?->label() }}</x-badge>
                                <x-badge variant="secondary">{{ $ev->type?->label() }}</x-badge>
                                <span>{{ $ev->starts_at?->format('d/m/Y H:i') }} – {{ $ev->ends_at?->format('H:i') }}</span>
                                <span>· {{ $ev->organizer?->name ?? '—' }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <x-empty-state message="Chưa có lịch hẹn." icon="calendar" />
                @endforelse
            </x-card>
        @endif

        {{-- Hợp đồng & doanh thu --}}
        @if (class_exists(\App\Models\Deal::class))
            <x-card title="Hợp đồng & doanh thu" class="mt-6">
                @if (! $customer->deal)
                    @can('create', \App\Models\Deal::class)
                        <x-slot:actions>
                            <x-button variant="secondary" size="sm" data-modal-form="{{ route('deals.create', ['customer_id' => $customer->id]) }}" data-modal-title="Tạo deal">
                                <x-icon name="plus" class="h-4 w-4" /> Tạo deal
                            </x-button>
                        </x-slot:actions>
                    @endcan
                @endif
                @if ($customer->deal)
                    <div class="flex items-center justify-between text-sm">
                        <div>
                            <a href="{{ route('deals.show', $customer->deal) }}" class="font-mono text-brand-600 hover:underline">{{ $customer->deal->code }}</a>
                            <div class="text-xs text-gray-500 mt-0.5 flex items-center gap-2">
                                <x-badge :variant="$customer->deal->stage?->badgeVariant() ?? 'secondary'">{{ $customer->deal->stage?->label() }}</x-badge>
                                <span>{{ $customer->deal->title }}</span>
                            </div>
                        </div>
                        <div class="font-semibold tabular-nums">{{ number_format($customer->deal->total_amount) }} đ</div>
                    </div>
                @else
                    <x-empty-state message="Chưa có deal cho khách hàng này." icon="deals" />
                @endif
            </x-card>
        @endif

        {{-- Người liên hệ --}}
        @if (class_exists(\App\Models\Contact::class))
            <x-card title="Người liên hệ" class="mt-6">
                @can('create', [\App\Models\Contact::class, $customer])
                    <x-slot:actions>
                        <x-button variant="secondary" size="sm" data-modal-form="{{ route('customers.contacts.create', $customer) }}" data-modal-title="Thêm người liên hệ">
                            <x-icon name="plus" class="h-4 w-4" /> Thêm
                        </x-button>
                    </x-slot:actions>
                @endcan
                @forelse ($customer->contacts as $c)
                    <div class="flex items-center justify-between border-b border-gray-100 py-2 last:border-0 text-sm">
                        <div class="min-w-0">
                            <span class="inline-flex items-center gap-2">
                                <a href="{{ route('contacts.show', $c) }}" class="font-medium text-brand-600 hover:underline">
                                    {{ $c->full_name }}
                                </a>
                                @if ($c->is_primary)
                                    <x-badge variant="success">Chính</x-badge>
                                @endif
                            </span>
                            <div class="text-xs text-gray-500">
                                {{ $c->position ?? '' }}
                                @if ($c->email) · {{ $c->email }} @endif
                                @if ($c->phone) · {{ $c->phone }} @endif
                            </div>
                        </div>
                        <div class="flex gap-2">
                            @can('update', $c)
                                <button type="button" data-modal-form="{{ route('contacts.edit', $c) }}" data-modal-title="Sửa người liên hệ" class="text-xs text-gray-600 hover:text-gray-900">Sửa</button>
                            @endcan
                        </div>
                    </div>
                @empty
                    <x-empty-state message="Chưa có người liên hệ." icon="users" />
                @endforelse
            </x-card>
        @endif

        <div class="mt-6">
            <a href="{{ route('customers.index') }}" class="inline-flex items-center gap-1 text-sm text-brand-600 hover:underline">
                <x-icon name="arrow-left" class="h-4 w-4" /> Quay lại danh sách
            </a>
        </div>
    </div>
</x-layouts.app>
