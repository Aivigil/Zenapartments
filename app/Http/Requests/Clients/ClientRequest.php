<?php

namespace App\Http\Requests\Clients;

use Illuminate\Foundation\Http\FormRequest;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('clients.manage');
    }

    public function rules(): array
    {
        $clientId = $this->route('client')?->id;

        return [
            // Code is optional on create — controller auto-generates if missing.
            'code' => ['nullable', 'string', 'max:32', 'unique:clients,code,' . ($clientId ?? 'NULL')],
            'full_name' => ['required', 'string', 'max:255'],
            'father_or_husband_name' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'nationality' => ['nullable', 'string', 'max:64'],
            'country_of_residence' => ['nullable', 'string', 'size:2'],
            // CNIC: Pakistani format is 13 digits (optionally with dashes). Permissive validation.
            'cnic' => ['nullable', 'string', 'regex:/^[0-9\-]{10,20}$/'],
            'passport' => ['nullable', 'string', 'max:20'],
            'primary_phone' => ['required', 'string', 'max:32'],
            'alt_phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:128'],
            'country' => ['nullable', 'string', 'size:2'],
            'kyc_status' => ['required', 'in:pending,verified,rejected'],
            'preferences' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
