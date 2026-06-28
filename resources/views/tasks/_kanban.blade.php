{{--
    Partial: Kanban board cho task index (kéo–thả đổi status qua SortableJS).
    Yêu cầu biến:
      - $columns (array<string, Collection<Task>>) — key = TaskStatus value
      - $statuses (array<TaskStatus>) — để giữ thứ tự cột ổn định
--}}
@php
    // Map màu cho header của từng cột để dễ phân biệt thị giác.
    $columnAccent = [
        \App\Enums\TaskStatus::Pending->value => 'border-t-gray-400',
        \App\Enums\TaskStatus::InProgress->value => 'border-t-brand-500',
        \App\Enums\TaskStatus::Completed->value => 'border-t-green-500',
        \App\Enums\TaskStatus::Cancelled->value => 'border-t-red-400',
    ];
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
    @foreach ($statuses as $status)
        @php
            $tasksInColumn = $columns[$status->value] ?? collect();
            $accent = $columnAccent[$status->value] ?? 'border-t-gray-300';
        @endphp
        <div class="bg-gray-50 rounded-lg border border-gray-200 border-t-4 {{ $accent }} flex flex-col min-h-[200px]">
            <div class="px-3 py-2 flex items-center justify-between border-b border-gray-200">
                <div class="font-medium text-sm text-gray-700">{{ $status->label() }}</div>
                <span data-kanban-count="{{ $status->value }}"
                    class="text-xs text-gray-500 bg-white border border-gray-200 px-2 py-0.5 rounded-full tabular-nums">
                    {{ $tasksInColumn->count() }}
                </span>
            </div>

            <div data-kanban-column="{{ $status->value }}" class="flex flex-1 flex-col gap-2 p-2">
                @foreach ($tasksInColumn as $task)
                    @include('tasks._kanban_card', ['task' => $task])
                @endforeach
                <div data-kanban-empty
                    class="{{ $tasksInColumn->isEmpty() ? '' : 'hidden' }} pointer-events-none rounded-md border-2 border-dashed border-gray-200 py-8 text-center text-xs text-gray-400">
                    Kéo thẻ vào đây
                </div>
            </div>
        </div>
    @endforeach
</div>
