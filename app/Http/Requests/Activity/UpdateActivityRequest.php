<?php

namespace App\Http\Requests\Activity;

use App\Enums\ActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('activity')) ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(ActivityType::values())],
            'subject' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'happened_at' => ['required', 'date'],
        ];
    }
}
