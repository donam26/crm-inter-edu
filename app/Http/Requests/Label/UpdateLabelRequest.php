<?php

namespace App\Http\Requests\Label;

use App\Models\Label;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('label')) ?? false;
    }

    public function rules(): array
    {
        /** @var Label $label */
        $label = $this->route('label');

        return [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('labels', 'name')
                    ->where('branch_id', $label->branch_id)
                    ->ignore($label->id),
            ],
            'color' => ['required', Rule::in(['secondary', 'primary', 'success', 'warning', 'danger'])],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'Chi nhánh đã có nhãn với tên này.'];
    }
}
