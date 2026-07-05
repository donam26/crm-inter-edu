<x-layouts.app title="Chi tiết task" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Công việc', 'url' => route('tasks.index')],
    ['label' => $task->title],
]">
    @php
        $clTotal = $task->checklistItems->count();
        $clDone = $task->checklistItems->where('is_done', true)->count();
        $clPct = $clTotal > 0 ? round($clDone / $clTotal * 100) : 0;
    @endphp

    <div class="max-w-5xl">
        <x-page-header :title="$task->title">
            @can('complete', $task)
                @if ($task->status?->isOpen())
                    <form method="POST" action="{{ route('tasks.complete', $task) }}" class="inline">
                        @csrf
                        <x-button type="submit" variant="primary"><x-icon name="check" class="h-4 w-4" /> Hoàn thành</x-button>
                    </form>
                @elseif ($task->status === \App\Enums\TaskStatus::Completed)
                    <form method="POST" action="{{ route('tasks.reopen', $task) }}" class="inline">
                        @csrf
                        <x-button type="submit" variant="secondary">Mở lại</x-button>
                    </form>
                @endif
            @endcan
            @can('update', $task)
                <x-button variant="secondary" data-modal-form="{{ route('tasks.edit', $task) }}" data-modal-title="Sửa task">Sửa</x-button>
            @endcan
            @can('delete', $task)
                <form method="POST" action="{{ route('tasks.destroy', $task) }}"
                    onsubmit="return confirm('Xoá task này?');" class="inline">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger">Xoá</x-button>
                </form>
            @endcan
        </x-page-header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- ───────────────── Cột chính ───────────────── --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Mô tả --}}
                <x-card>
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">Mô tả</h3>
                    <div class="text-sm text-gray-700 whitespace-pre-line">{{ $task->description ?: '—' }}</div>
                </x-card>

                {{-- Checklist --}}
                <x-card>
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">Checklist</h3>
                        <span class="text-xs text-gray-500 tabular-nums">{{ $clDone }}/{{ $clTotal }}</span>
                    </div>
                    @if ($clTotal > 0)
                        <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full bg-green-500 transition-all" style="width: {{ $clPct }}%"></div>
                        </div>
                    @endif

                    <ul class="mt-3 space-y-1.5">
                        @forelse ($task->checklistItems as $item)
                            <li class="flex items-center gap-2">
                                @can('update', $task)
                                    <form method="POST" action="{{ route('checklist.update', $item) }}" class="flex items-center">
                                        @csrf
                                        @method('PATCH')
                                        <input type="checkbox" onchange="this.form.submit()" @checked($item->is_done)
                                            class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                    </form>
                                @else
                                    <input type="checkbox" disabled @checked($item->is_done)
                                        class="rounded border-gray-300 text-gray-400">
                                @endcan
                                <span class="flex-1 text-sm {{ $item->is_done ? 'line-through text-gray-400' : 'text-gray-700' }}">{{ $item->title }}</span>
                                @can('update', $task)
                                    <form method="POST" action="{{ route('checklist.destroy', $item) }}"
                                        onsubmit="return confirm('Xoá mục này?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-gray-300 hover:text-red-500" title="Xoá">
                                            <x-icon name="x-mark" class="h-4 w-4" />
                                        </button>
                                    </form>
                                @endcan
                            </li>
                        @empty
                            <li class="text-sm text-gray-400">Chưa có mục nào.</li>
                        @endforelse
                    </ul>

                    @can('update', $task)
                        <form method="POST" action="{{ route('tasks.checklist.store', $task) }}" class="mt-3 flex gap-2">
                            @csrf
                            <input type="text" name="title" required maxlength="255" placeholder="Thêm mục checklist…"
                                class="flex-1 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                            <x-button type="submit" variant="secondary" size="sm">Thêm</x-button>
                        </form>
                    @endcan
                </x-card>

                {{-- Bình luận --}}
                <x-card>
                    <h3 class="text-sm font-semibold text-gray-900">Bình luận</h3>
                    <div class="mt-3 space-y-4">
                        @forelse ($task->comments as $comment)
                            <div class="flex gap-3">
                                <span class="inline-flex h-8 w-8 flex-none items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700 uppercase">
                                    {{ mb_substr($comment->author?->name ?? '?', 0, 1) }}
                                </span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-900">{{ $comment->author?->name ?? '—' }}</span>
                                        <span class="text-xs text-gray-400">{{ $comment->created_at->format('d/m/Y H:i') }}</span>
                                        @can('delete', $comment)
                                            <form method="POST" action="{{ route('comments.destroy', $comment) }}"
                                                onsubmit="return confirm('Xoá bình luận?');" class="ml-auto inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs text-gray-400 hover:text-red-500">Xoá</button>
                                            </form>
                                        @endcan
                                    </div>
                                    <div class="mt-0.5 text-sm text-gray-700 whitespace-pre-line break-words">{{ $comment->body }}</div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400">Chưa có bình luận nào.</p>
                        @endforelse
                    </div>

                    <form method="POST" action="{{ route('tasks.comments.store', $task) }}" class="mt-4">
                        @csrf
                        <x-textarea name="body" :rows="2" placeholder="Viết bình luận…" required margin="mb-0" />
                        <div class="flex justify-end mt-2">
                            <x-button type="submit" variant="primary" size="sm">Gửi</x-button>
                        </div>
                    </form>
                </x-card>
            </div>

            {{-- ───────────────── Sidebar ───────────────── --}}
            <div class="space-y-6">
                {{-- Thông tin --}}
                <x-card>
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Thông tin</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between gap-2"><span class="text-gray-500">Trạng thái</span>
                            <x-badge :variant="$task->status?->badgeVariant() ?? 'secondary'">{{ $task->status?->label() }}</x-badge>
                        </div>
                        <div class="flex justify-between gap-2"><span class="text-gray-500">Ưu tiên</span>
                            <x-badge :variant="$task->priority?->badgeVariant() ?? 'secondary'">{{ $task->priority?->label() }}</x-badge>
                        </div>
                        <div class="flex justify-between gap-2"><span class="text-gray-500">Loại</span><span class="text-gray-800">{{ $task->type?->label() }}</span></div>
                        <div class="flex justify-between gap-2"><span class="text-gray-500">Người được giao</span><span class="text-gray-800">{{ $task->assignee?->name ?? '—' }}</span></div>
                        <div class="flex justify-between gap-2"><span class="text-gray-500">Người tạo</span><span class="text-gray-800">{{ $task->creator?->name ?? '—' }}</span></div>
                        @if ($task->start_at)
                            <div class="flex justify-between gap-2"><span class="text-gray-500">Bắt đầu</span><span class="text-gray-800">{{ $task->start_at->format('d/m/Y H:i') }}</span></div>
                        @endif
                        <div class="flex justify-between gap-2 items-center"><span class="text-gray-500">Hạn chót</span>
                            <span class="text-gray-800">{{ $task->due_at?->format('d/m/Y H:i') }}
                                @if ($task->is_overdue)<x-badge variant="danger" class="ml-1">Quá hạn</x-badge>@endif
                            </span>
                        </div>
                        @if ($task->status === \App\Enums\TaskStatus::Completed)
                            <div class="flex justify-between gap-2"><span class="text-gray-500">Hoàn thành</span><span class="text-gray-800">{{ $task->completed_at?->format('d/m/Y H:i') }}</span></div>
                        @endif
                        <div class="flex justify-between gap-2"><span class="text-gray-500">Chi nhánh</span><span class="text-gray-800">{{ $task->branch?->name }}</span></div>
                        @if ($task->customer)
                            <div class="flex justify-between gap-2"><span class="text-gray-500">Lead</span>
                                <a href="{{ route('customers.show', $task->customer) }}" class="text-brand-600 hover:underline text-right">{{ $task->customer->name }}</a>
                            </div>
                        @endif
                    </div>

                    {{-- Nhãn --}}
                    <div class="mt-4 pt-4 border-t border-gray-100" x-data="{ editing: false }">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-gray-500">Nhãn</span>
                            @can('update', $task)
                                <button type="button" @click="editing = !editing" class="text-xs font-medium text-brand-600 hover:underline">
                                    <span x-show="!editing">Sửa</span><span x-show="editing" x-cloak>Đóng</span>
                                </button>
                            @endcan
                        </div>

                        <div x-show="!editing" class="flex flex-wrap gap-1">
                            @forelse ($task->labels as $label)
                                <x-badge :variant="$label->badgeVariant()">{{ $label->name }}</x-badge>
                            @empty
                                <span class="text-sm text-gray-400">Chưa gắn nhãn.</span>
                            @endforelse
                        </div>

                        @can('update', $task)
                            <form x-show="editing" x-cloak method="POST" action="{{ route('tasks.labels.sync', $task) }}" class="space-y-2">
                                @csrf
                                @forelse ($branchLabels as $label)
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="label_ids[]" value="{{ $label->id }}"
                                            @checked($task->labels->contains($label->id))
                                            class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                        <x-badge :variant="$label->badgeVariant()">{{ $label->name }}</x-badge>
                                    </label>
                                @empty
                                    <p class="text-xs text-gray-400">Chi nhánh chưa có nhãn nào.
                                        @can('create', App\Models\Label::class)<a href="{{ route('labels.index') }}" class="text-brand-600 hover:underline">Tạo nhãn</a>@endcan
                                    </p>
                                @endforelse
                                <x-button type="submit" variant="secondary" size="sm">Lưu nhãn</x-button>
                            </form>
                        @endcan
                    </div>

                    {{-- Theo dõi --}}
                    <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between">
                        <span class="text-sm text-gray-500">Theo dõi ({{ $task->watchers->count() }})</span>
                        @if ($isWatching)
                            <form method="POST" action="{{ route('tasks.unwatch', $task) }}">
                                @csrf
                                <button type="submit" class="text-xs font-medium text-gray-500 hover:text-gray-700">Bỏ theo dõi</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('tasks.watch', $task) }}">
                                @csrf
                                <button type="submit" class="text-xs font-medium text-brand-600 hover:underline">Theo dõi</button>
                            </form>
                        @endif
                    </div>
                </x-card>

                {{-- Lịch sử --}}
                <x-card>
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Lịch sử</h3>
                    <ol class="space-y-3">
                        @forelse ($activityFeed as $log)
                            <li class="text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-700">{{ $log['causer'] }}</span>
                                    <span class="text-gray-400">{{ $log['time']->format('d/m H:i') }}</span>
                                </div>
                                @if ($log['event'] === 'created')
                                    <div class="text-gray-500">Tạo công việc</div>
                                @else
                                    @foreach ($log['changes'] as $change)
                                        <div class="text-gray-500">
                                            {{ $change['label'] }}:
                                            <span class="text-gray-400">{{ $change['from'] }}</span>
                                            <span class="text-gray-300">→</span>
                                            <span class="text-gray-700">{{ $change['to'] }}</span>
                                        </div>
                                    @endforeach
                                @endif
                            </li>
                        @empty
                            <li class="text-sm text-gray-400">Chưa có thay đổi nào được ghi.</li>
                        @endforelse
                    </ol>
                </x-card>
            </div>
        </div>

        <div class="mt-6">
            <a href="{{ route('tasks.index') }}" class="inline-flex items-center gap-1 text-sm text-brand-600 hover:underline">
                <x-icon name="arrow-left" class="h-4 w-4" /> Quay lại danh sách
            </a>
        </div>
    </div>
</x-layouts.app>
