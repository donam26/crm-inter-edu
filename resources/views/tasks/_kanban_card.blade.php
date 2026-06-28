{{--
    Partial: 1 card task trong kanban board. Có thể kéo–thả để đổi status.
    Yêu cầu biến:
      - $task (App\Models\Task)
--}}
@php
    $isOverdue = $task->is_overdue;
    $borderClass = $isOverdue ? 'border-red-300' : 'border-gray-200';
@endphp
<div
    data-task-id="{{ $task->id }}"
    data-status-url="{{ route('tasks.status', $task) }}"
    class="kanban-card bg-white rounded-md border {{ $borderClass }} shadow-sm p-3 select-none"
>
    <div class="flex items-start justify-between gap-2">
        <a href="{{ route('tasks.show', $task) }}"
            class="font-medium text-sm text-brand-700 hover:underline line-clamp-2">
            {{ $task->title }}
        </a>
        @if ($task->priority)
            <x-badge :variant="$task->priority->badgeVariant()">
                {{ $task->priority->label() }}
            </x-badge>
        @endif
    </div>

    @if ($task->lead)
        <div class="text-xs text-gray-500 mt-1">
            <a href="{{ route('leads.show', $task->lead) }}" class="hover:underline">
                {{ $task->lead->school_name }}
            </a>
        </div>
    @endif

    <div class="mt-2 flex items-center justify-between text-xs text-gray-600">
        <span class="truncate">{{ $task->assignee?->name ?? '—' }}</span>
        @if ($task->due_at)
            <span class="{{ $isOverdue ? 'text-red-600 font-medium' : '' }}">
                {{ $task->due_at->format('d/m H:i') }}@if ($isOverdue) · Quá hạn @endif
            </span>
        @endif
    </div>
</div>
