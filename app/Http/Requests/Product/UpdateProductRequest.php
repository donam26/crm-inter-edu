<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('product')) ?? false;
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('products', 'code')
                    ->where(fn ($q) => $q->where('branch_id', $product->branch_id))
                    ->ignore($product->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit_price' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
