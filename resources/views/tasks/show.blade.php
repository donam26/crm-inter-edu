<x-layouts.app title="Chi tiết task" :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Công việc', 'url' => route('tasks.index')],
    ['label' => $task->title],
]">
    <div class="max-w-3xl">
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

        <x-card>
            <div class="space-y-3 text-sm">
                <div class="flex"><span class="w-44 text-gray-500">Trạng thái:</span>
                    <x-badge :variant="$task->status?->badgeVariant() ?? 'secondary'">{{ $task->status?->label() }}</x-badge>
                </div>
                <div class="flex"><span class="w-44 text-gray-500">Loại:</span><span>{{ $task->type?->label() }}</span></div>
                <div class="flex"><span class="w-44 text-gray-500">Ưu tiên:</span>
                    <x-badge :variant="$task->priority?->badgeVariant() ?? 'secondary'">{{ $task->priority?->label() }}</x-badge>
                </div>
                <div class="flex items-center"><span class="w-44 text-gray-500">Hạn chót:</span>
                    <span>{{ $task->due_at?->format('d/m/Y H:i') }}</span>
                    @if ($task->is_overdue)
                        <x-badge variant="danger" class="ml-2">Quá hạn</x-badge>
                    @endif
                </div>
                <div class="flex"><span class="w-44 text-gray-500">Người được giao:</span><span>{{ $task->assignee?->name ?? '—' }}</span></div>
                <div class="flex"><span class="w-44 text-gray-500">Người tạo:</span><span>{{ $task->creator?->name ?? '—' }}</span></div>
                @if ($task->status === \App\Enums\TaskStatus::Completed)
                    <div class="flex"><span class="w-44 text-gray-500">Hoàn thành lúc:</span><span>{{ $task->completed_at?->format('d/m/Y H:i') }}</span></div>
                    <div class="flex"><span class="w-44 text-gray-500">Người hoàn thành:</span><span>{{ $task->completer?->name ?? '—' }}</span></div>
                @endif
                <div class="flex"><span class="w-44 text-gray-500">Chi nhánh:</span><span>{{ $task->branch?->name }}</span></div>
                @if ($task->lead)
                    <div class="flex"><span class="w-44 text-gray-500">Lead:</span>
                        <a href="{{ route('leads.show', $task->lead) }}" class="text-brand-600 hover:underline">
                            {{ $task->lead->school_name }}
                        </a>
                    </div>
                @endif
                @if ($task->reminder_enabled)
                    <div class="flex"><span class="w-44 text-gray-500">Nhắc lúc:</span><span>{{ $task->remind_at?->format('d/m/Y H:i') ?? '—' }}</span></div>
                @endif
                <div>
                    <div class="text-gray-500 mb-1">Mô tả:</div>
                    <div class="whitespace-pre-line">{{ $task->description ?? '—' }}</div>
                </div>
            </div>
        </x-card>

        <div class="mt-6">
            <a href="{{ route('tasks.index') }}" class="inline-flex items-center gap-1 text-sm text-brand-600 hover:underline">
                <x-icon name="arrow-left" class="h-4 w-4" /> Quay lại danh sách
            </a>
        </div>
    </div>
</x-layouts.app>
