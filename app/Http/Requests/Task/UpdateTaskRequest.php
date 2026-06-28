<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('task')) ?? false;
    }

    public function rules(): array
    {
        // Update cho phép due_at lùi về quá khứ (ví dụ chỉnh sai sót lịch sử),
        // status có thể là bất kỳ giá trị hợp lệ. Việc chuyển sang Completed
        // qua endpoint riêng (TaskController@complete) để đảm bảo set kèm
        // completed_at/completed_by atomically.
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', 'string', Rule::in(TaskType::values())],
            'priority' => ['required', 'string', Rule::in(TaskPriority::values())],
            'status' => ['required', 'string', Rule::in(TaskStatus::values())],
            'due_at' => ['required', 'date'],
            'assigned_user_id' => ['required', 'integer', 'exists:users,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'reminder_enabled' => ['boolean'],
            'remind_at' => ['nullable', 'date', 'before_or_equal:due_at'],
        ];
    }

    public function messages(): array
    {
        return [
            'remind_at.before_or_equal' => 'Thời điểm nhắc phải trước hoặc bằng hạn chót.',
            'title.min' => 'Tiêu đề phải có ít nhất 3 ký tự.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reminder_enabled' => $this->boolean('reminder_enabled'),
        ]);
    }
}
