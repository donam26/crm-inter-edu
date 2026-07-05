<?php

namespace App\Http\Requests\Customer;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) ?? false;
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
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', Rule::in(CustomerStatus::values())],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string'],
        ];
    }
}
