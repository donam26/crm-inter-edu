<?php

namespace App\Http\Requests\Lead;

use App\Enums\LeadStatus;
use App\Enums\SchoolLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('lead')) ?? false;
    }

    public function rules(): array
    {
        return [
            'school_name' => ['required', 'string', 'max:255'],
            'school_level' => ['required', 'string', Rule::in(SchoolLevel::values())],
            'student_size' => ['nullable', 'integer', 'min:0'],
            'address' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'string', Rule::in(LeadStatus::values())],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string'],
        ];
    }
}
