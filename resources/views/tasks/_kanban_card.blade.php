{{--
    Partial: 1 card task trong kanban board. Có thể kéo–thả để đổi status.
    Yêu cầu biến:
      - $task (App\Models\Task) — nên eager-load 'labels' + withCount checklist
        (checklist_items_count, checklist_done_count) như TaskService::buildQuery.
--}}
@php
    $isOverdue = $task->is_overdue;
    $borderClass = $isOverdue ? 'border-red-300' : 'border-gray-200';
    $checklistTotal = $task->checklist_items_count ?? 0;
    $checklistDone = $task->checklist_done_count ?? 0;
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

    @if ($task->labels->isNotEmpty())
        <div class="mt-2 flex flex-wrap gap-1">
            @foreach ($task->labels as $label)
                <x-badge :variant="$label->badgeVariant()">{{ $label->name }}</x-badge>
            @endforeach
        </div>
    @endif

    @if ($task->lead)
        <div class="text-xs text-gray-500 mt-2">
            <a href="{{ route('leads.show', $task->lead) }}" class="hover:underline">
                {{ $task->lead->school_name }}
            </a>
        </div>
    @endif

    <div class="mt-2 flex items-center justify-between gap-2 text-xs text-gray-600">
        <span class="truncate">{{ $task->assignee?->name ?? '—' }}</span>
        <div class="flex flex-none items-center gap-2">
            @if ($checklistTotal > 0)
                <span class="inline-flex items-center gap-0.5 {{ $checklistDone === $checklistTotal ? 'text-green-600 font-medium' : '' }}">
                    <x-icon name="check" class="h-3.5 w-3.5" />{{ $checklistDone }}/{{ $checklistTotal }}
                </span>
            @endif
            @if ($task->due_at)
                <span class="{{ $isOverdue ? 'text-red-600 font-medium' : '' }}">
                    {{ $task->due_at->format('d/m H:i') }}@if ($isOverdue) · Quá hạn @endif
                </span>
            @endif
        </div>
    </div>
</div>
