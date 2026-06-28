<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Task::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', 'string', Rule::in(TaskType::values())],
            'priority' => ['required', 'string', Rule::in(TaskPriority::values())],
            'status' => ['nullable', 'string', Rule::in([
                TaskStatus::Pending->value,
                TaskStatus::InProgress->value,
            ])],
            // Khi tạo mới: due_at không được trong quá khứ.
            'due_at' => ['required', 'date', 'after_or_equal:now'],
            'assigned_user_id' => ['required', 'integer', 'exists:users,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'reminder_enabled' => ['boolean'],
            'remind_at' => ['nullable', 'date', 'before_or_equal:due_at'],
        ];
    }

    public function messages(): array
    {
        return [
            'due_at.after_or_equal' => 'Hạn chót không được ở quá khứ.',
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
