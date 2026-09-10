<?php

namespace App\Http\Requests\Catalog;

use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required', 'string', 'max:100',
                Rule::unique('products', 'sku')
                    ->where('business_id', Tenant::id())
                    ->ignore($productId),
            ],
            'barcode' => ['nullable', 'string', 'max:100'],
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('business_id', Tenant::id()),
            ],
            'brand_id' => [
                'nullable',
                Rule::exists('brands', 'id')->where('business_id', Tenant::id()),
            ],
            'unit_id' => [
                'required',
                Rule::exists('units', 'id')->where('business_id', Tenant::id()),
            ],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'min_stock_level' => ['sometimes', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
