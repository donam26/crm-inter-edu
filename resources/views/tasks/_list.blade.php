{{--
    Partial: list view cho task index.
    Yêu cầu biến:
      - $tasks (LengthAwarePaginator)
--}}
<x-table :headers="['Tiêu đề', 'Người được giao', 'Hạn', 'Ưu tiên', 'Trạng thái', '']">
    @forelse ($tasks as $task)
        @php
            $rowClass = $task->is_overdue ? 'bg-red-50' : '';
        @endphp
        <tr class="{{ $rowClass }}">
            <td class="px-4 py-3">
                <a href="{{ route('tasks.show', $task) }}" class="font-medium text-brand-600 hover:underline">
                    {{ $task->title }}
                </a>
                @if ($task->customer)
                    <div class="text-xs text-gray-500 mt-0.5">
                        Lead:
                        <a href="{{ route('customers.show', $task->customer) }}" class="hover:underline">
                            {{ $task->customer->name }}
                        </a>
                    </div>
                @endif
            </td>
            <td class="px-4 py-3 text-sm">{{ $task->assignee?->name ?? '—' }}</td>
            <td class="px-4 py-3 text-sm">
                {{ $task->due_at?->format('d/m/Y H:i') }}
                @if ($task->is_overdue)
                    <x-badge variant="danger">Quá hạn</x-badge>
                @endif
            </td>
            <td class="px-4 py-3">
                <x-badge :variant="$task->priority?->badgeVariant() ?? 'secondary'">
                    {{ $task->priority?->label() }}
                </x-badge>
            </td>
            <td class="px-4 py-3">
                <x-badge :variant="$task->status?->badgeVariant() ?? 'secondary'">
                    {{ $task->status?->label() }}
                </x-badge>
            </td>
            <td class="px-4 py-3 text-right">
                @can('complete', $task)
                    @if ($task->status?->isOpen())
                        <form method="POST" action="{{ route('tasks.complete', $task) }}" class="inline">
                            @csrf
                            <button type="submit"
                                class="text-xs text-green-600 hover:text-green-800">Hoàn thành</button>
                        </form>
                    @endif
                @endcan
            </td>
        </tr>
    @empty
        <x-table.empty :colspan="6" message="Chưa có task nào." icon="tasks" />
    @endforelse
</x-table>

<div class="mt-4">
    {{ $tasks->links() }}
</div>
