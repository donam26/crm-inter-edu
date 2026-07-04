<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class StoreChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Thêm mục checklist = sửa task → dùng TaskPolicy@update.
        return $this->user()?->can('update', $this->route('task')) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
        ];
    }
}
