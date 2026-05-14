<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class UnitCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('unit_categories.manage');
    }

    public function rules(): array
    {
        $id = $this->route('unitCategory')?->id;
        return [
            'code' => ['required', 'string', 'max:32', 'unique:unit_categories,code,' . ($id ?? 'NULL')],
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'in:plot,chalet,apartment,studio'],
            'description' => ['nullable', 'string', 'max:2000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
