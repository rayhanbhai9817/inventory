<?php

namespace App\Http\Requests\Pricing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Price and effective_date are deliberately not editable once recorded
 * — the ledger is append-only (see ProductPrice model docblock). The
 * `update` action only touches `notes`, so this request only validates
 * the fields the create action needs.
 */
class ProductPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'effective_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
