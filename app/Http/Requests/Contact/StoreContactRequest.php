<?php

namespace App\Http\Requests\Contact;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [Contact::class, $this->route('customer')]) ?? false;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:50', 'required_without:email'],
            'is_primary' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without' => 'Phải nhập ít nhất một trong hai trường email hoặc số điện thoại.',
            'phone.required_without' => 'Phải nhập ít nhất một trong hai trường email hoặc số điện thoại.',
        ];
    }
}
