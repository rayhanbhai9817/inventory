<?php

namespace App\Http\Requests\Supplier;

use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isCreate = $this->isMethod('post');

        return [
            'supplier_id' => [
                $isCreate ? 'required' : 'sometimes',
                Rule::exists('suppliers', 'id')->where('business_id', Tenant::id()),
            ],
            'product_id' => [
                $isCreate ? 'required' : 'sometimes',
                Rule::exists('products', 'id')->where('business_id', Tenant::id()),
            ],
            'supplier_sku' => ['nullable', 'string', 'max:100'],
            'supplier_product_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_primary' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
