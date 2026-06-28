<?php

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [Invoice::class, $this->route('deal')]) ?? false;
    }

    public function rules(): array
    {
        return [
            'due_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ];
    }
}
