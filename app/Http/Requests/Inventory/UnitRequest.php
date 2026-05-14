<?php

namespace App\Http\Requests\Inventory;

use App\Enums\UnitStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('units.manage');
    }

    public function rules(): array
    {
        $projectId = $this->input('project_id');
        $unitId = $this->route('unit')?->id;
        return [
            'project_id' => ['required', 'exists:projects,id'],
            'block_id' => ['nullable', 'exists:blocks,id'],
            'unit_category_id' => ['required', 'exists:unit_categories,id'],
            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('units', 'code')
                    ->where(fn ($q) => $q->where('project_id', $projectId))
                    ->ignore($unitId),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'size_value' => ['nullable', 'numeric', 'min:0'],
            'size_unit' => ['nullable', 'in:marla,kanal,sqft,sqm'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['required', new Enum(UnitStatus::class)],
            'attributes' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Map UI's major-unit price to minor units before persistence.
     */
    protected function passedValidation(): void
    {
        $this->merge([
            'base_price_minor' => money_major_to_minor($this->input('base_price')),
        ]);
    }
}
