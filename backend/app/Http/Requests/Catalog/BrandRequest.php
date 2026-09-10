<?php

namespace App\Http\Requests\Catalog;

use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'slug' => [
                Rule::unique('brands', 'slug')
                    ->where('business_id', Tenant::id())
                    ->ignore($this->route('brand')?->id),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::slug($this->input('name', ''))]);
    }
}
