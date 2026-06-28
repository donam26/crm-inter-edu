<?php

namespace App\Http\Requests\Payment;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('payment')) ?? false;
    }

    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'integer', 'min:1'],
            'method' => ['nullable', 'string', Rule::in(PaymentMethod::values())],
            'paid_at' => ['nullable', 'date'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
        ];
    }
}
