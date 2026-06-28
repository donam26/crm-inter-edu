<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('invoice')) ?? false;
    }

    public function rules(): array
    {
        return [
            'due_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ];
    }
}
