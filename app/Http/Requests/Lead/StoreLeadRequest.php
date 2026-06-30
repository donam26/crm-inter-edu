<?php

namespace App\Http\Requests\Lead;

use App\Enums\LeadStatus;
use App\Enums\SchoolLevel;
use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Lead::class) ?? false;
    }

    public function rules(): array
    {
        return [
            // Chỉ bắt buộc khi user không thuộc chi nhánh nào (super-admin);
            // user có branch sẽ được auto-gán ở service và bỏ qua giá trị này.
            'branch_id' => [
                Rule::requiredIf(fn () => $this->user()?->branch_id === null),
                'integer',
                'exists:branches,id',
            ],
            'school_name' => ['required', 'string', 'max:255'],
            'school_level' => ['required', 'string', Rule::in(SchoolLevel::values())],
            'student_size' => ['nullable', 'integer', 'min:0'],
            'address' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', Rule::in(LeadStatus::values())],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string'],
        ];
    }
}
