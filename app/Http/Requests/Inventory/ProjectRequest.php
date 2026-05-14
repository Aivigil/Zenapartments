<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('projects.manage');
    }

    public function rules(): array
    {
        $projectId = $this->route('project')?->id;
        return [
            'code' => ['required', 'string', 'max:32', 'unique:projects,code,' . ($projectId ?? 'NULL')],
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2'],
            'status' => ['required', 'in:active,paused,completed'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
