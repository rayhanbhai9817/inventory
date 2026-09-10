<?php

namespace App\Http\Requests\Stock;

use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                Rule::exists('products', 'id')->where('business_id', Tenant::id()),
            ],
            'boxes' => ['required', 'integer', 'min:1'],
            'units_per_box' => ['required', 'integer', 'min:1'],
            'received_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'supplier_id' => [
                'nullable',
                Rule::exists('suppliers', 'id')->where('business_id', Tenant::id()),
            ],
        ];
    }
}
