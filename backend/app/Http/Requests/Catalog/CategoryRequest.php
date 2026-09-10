<?php

namespace App\Http\Requests\Catalog;

use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('business_id', Tenant::id()),
            ],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'slug' => [
                Rule::unique('categories', 'slug')
                    ->where('business_id', Tenant::id())
                    ->ignore($categoryId),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->input('name', '')),
        ]);
    }
}
