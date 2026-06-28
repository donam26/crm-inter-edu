<?php

namespace App\Http\Requests\Deal;

use App\Models\DealItem;
use Illuminate\Foundation\Http\FormRequest;

class StoreDealItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [DealItem::class, $this->route('deal')]) ?? false;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'integer', 'min:0'],
            'discount_amount' => ['nullable', 'integer', 'min:0'],
            'tax_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
