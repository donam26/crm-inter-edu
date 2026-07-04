<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskChecklistItem;
use Illuminate\Support\Facades\DB;

class TaskChecklistService
{
    /**
     * Thêm 1 mục checklist vào cuối (position = max + 1).
     */
    public function add(Task $task, string $title): TaskChecklistItem
    {
        return DB::transaction(function () use ($task, $title) {
            $position = (int) $task->checklistItems()->max('position') + 1;

            return $task->checklistItems()->create([
                'title' => $title,
                'position' => $position,
            ]);
        });
    }

    /**
     * Lật trạng thái hoàn thành của 1 mục.
     */
    public function toggle(TaskChecklistItem $item): TaskChecklistItem
    {
        return DB::transaction(function () use ($item) {
            $item->update(['is_done' => ! $item->is_done]);

            return $item;
        });
    }

    public function remove(TaskChecklistItem $item): void
    {
        DB::transaction(fn () => $item->delete());
    }
}
