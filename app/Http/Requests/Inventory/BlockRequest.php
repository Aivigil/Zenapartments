<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('blocks.manage');
    }

    public function rules(): array
    {
        $projectId = $this->route('project')?->id ?? $this->input('project_id');
        $blockId = $this->route('block')?->id;
        return [
            'project_id' => ['required', 'exists:projects,id'],
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('blocks', 'code')
                    ->where(fn ($q) => $q->where('project_id', $projectId))
                    ->ignore($blockId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'block_type' => ['required', 'in:block,floor,sector'],
            'sort_order' => ['nullable', 'integer'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
