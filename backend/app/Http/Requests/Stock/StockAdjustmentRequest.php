<?php

namespace App\Http\Requests\Stock;

use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends FormRequest
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
            'direction' => ['required', Rule::in(['increase', 'decrease'])],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::in([
                'physical_count', 'damaged', 'lost', 'found', 'data_correction', 'other',
            ])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
