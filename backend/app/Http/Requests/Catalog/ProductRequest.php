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
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('business_id', Tenant::id()),
            ],
            'description' => ['nullable', 'string'],
            'min_stock_level' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
