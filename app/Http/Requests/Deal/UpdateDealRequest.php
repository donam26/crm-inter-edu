<?php

namespace App\Http\Requests\Deal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('deal')) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'expected_close_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ];
    }
}
