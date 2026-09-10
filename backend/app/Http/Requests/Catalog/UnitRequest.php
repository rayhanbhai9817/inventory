<?php

namespace App\Http\Requests\Catalog;

use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('units', 'name')
                    ->where('business_id', Tenant::id())
                    ->ignore($this->route('unit')?->id),
            ],
            'short_name' => ['required', 'string', 'max:20'],
        ];
    }
}
