<?php

namespace App\Http\Requests\Label;

use App\Models\Label;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Label::class) ?? false;
    }

    public function rules(): array
    {
        $user = $this->user();
        $branchId = $user?->hasRole('super-admin')
            ? $this->integer('branch_id')
            : $user?->branch_id;

        return [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('labels', 'name')->where('branch_id', $branchId),
            ],
            'color' => ['required', Rule::in(['secondary', 'primary', 'success', 'warning', 'danger'])],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'Chi nhánh đã có nhãn với tên này.'];
    }
}
