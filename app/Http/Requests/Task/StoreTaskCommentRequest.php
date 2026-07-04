<?php

namespace App\Http\Requests\Task;

use App\Models\TaskComment;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Uỷ quyền qua TaskCommentPolicy@create với task lấy từ route.
        return $this->user()?->can('create', [TaskComment::class, $this->route('task')]) ?? false;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
