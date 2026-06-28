<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    public function rules(): array
    {
        $branchId = $this->user()?->hasRole('super-admin')
            ? $this->input('branch_id')
            : $this->user()?->branch_id;

        return [
            // Super-admin có thể chọn branch; non-super-admin Service tự inject từ auth.
            'branch_id' => [
                $this->user()?->hasRole('super-admin') ? 'required' : 'nullable',
                'integer',
                'exists:branches,id',
            ],
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('products', 'code')->where(fn ($q) => $q->where('branch_id', $branchId)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit_price' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
