<?php

namespace App\Http\Requests\Deal;

use App\Models\Deal;
use Illuminate\Foundation\Http\FormRequest;

class StoreDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Deal::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'expected_close_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ];
    }
}
