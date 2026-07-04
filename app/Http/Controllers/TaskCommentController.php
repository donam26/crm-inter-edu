<?php

namespace App\Http\Controllers;

use App\Http\Requests\Task\StoreTaskCommentRequest;
use App\Models\Task;
use App\Models\TaskComment;
use App\Services\TaskCommentService;

class TaskCommentController extends Controller
{
    public function __construct(private TaskCommentService $service) {}

    public function store(StoreTaskCommentRequest $request, Task $task)
    {
        $this->service->create($task, $request->validated()['body']);

        return back()->with('success', 'Đã thêm bình luận.');
    }

    public function destroy(TaskComment $comment)
    {
        $this->authorize('delete', $comment);
        $this->service->delete($comment);

        return back()->with('success', 'Đã xoá bình luận.');
    }
}
