<?php

namespace App\Http\Controllers;

use App\Http\Requests\Task\StoreChecklistItemRequest;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Services\TaskChecklistService;

class TaskChecklistController extends Controller
{
    public function __construct(private TaskChecklistService $service) {}

    public function store(StoreChecklistItemRequest $request, Task $task)
    {
        $this->service->add($task, $request->validated()['title']);

        return back()->with('success', 'Đã thêm mục checklist.');
    }

    /** Toggle hoàn thành 1 mục. Quyền = sửa task cha. */
    public function update(TaskChecklistItem $item)
    {
        $this->authorize('update', $item->task);
        $this->service->toggle($item);

        return back();
    }

    public function destroy(TaskChecklistItem $item)
    {
        $this->authorize('update', $item->task);
        $this->service->remove($item);

        return back()->with('success', 'Đã xoá mục checklist.');
    }
}
