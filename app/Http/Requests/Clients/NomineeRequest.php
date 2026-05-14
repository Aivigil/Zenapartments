<?php

namespace App\Http\Requests\Clients;

use Illuminate\Foundation\Http\FormRequest;

class NomineeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('clients.manage');
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'relationship' => ['required', 'string', 'max:64'],
            'cnic' => ['nullable', 'string', 'regex:/^[0-9\-]{10,20}$/'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
