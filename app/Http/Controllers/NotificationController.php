<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(20),
        ]);
    }

    /**
     * Mở 1 thông báo: đánh dấu đã đọc rồi chuyển tới task liên quan.
     */
    public function open(Request $request, string $notification)
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->markAsRead();

        $taskId = $item->data['task_id'] ?? null;

        return $taskId
            ? redirect()->route('tasks.show', $taskId)
            : redirect()->route('notifications.index');
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Đã đánh dấu tất cả là đã đọc.');
    }
}
