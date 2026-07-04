<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Thông báo công việc — 1 lớp tham số hoá theo `type` (DRY thay vì 6 lớp).
 * Gửi qua database (chuông in-app) + mail. Mang primitive (không mang model)
 * để queue serialize gọn và không dính BranchScope khi worker chạy.
 */
class TaskNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $taskId,
        public string $taskTitle,
        public string $type,          // assigned|commented|status_changed|reminder|overdue
        public ?string $actorName = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->taskId,
            'task_title' => $this->taskTitle,
            'type' => $this->type,
            'actor' => $this->actorName,
            'message' => $this->message(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[CRM Inter-Edu] '.$this->message())
            ->line($this->message())
            ->action('Mở công việc', route('tasks.show', $this->taskId));
    }

    public function message(): string
    {
        $actor = $this->actorName ? $this->actorName.' ' : '';

        return match ($this->type) {
            'assigned' => $actor.'đã giao cho bạn công việc: '.$this->taskTitle,
            'commented' => $actor.'đã bình luận trong công việc: '.$this->taskTitle,
            'status_changed' => $actor.'đã đổi trạng thái công việc: '.$this->taskTitle,
            'reminder' => 'Nhắc: công việc “'.$this->taskTitle.'” sắp đến hạn.',
            'overdue' => 'Công việc “'.$this->taskTitle.'” đã quá hạn.',
            default => 'Cập nhật công việc: '.$this->taskTitle,
        };
    }
}
