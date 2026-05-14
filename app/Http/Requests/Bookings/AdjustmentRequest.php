<?php

namespace App\Http\Requests\Bookings;

use Illuminate\Foundation\Http\FormRequest;

class AdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('adjustments.request');
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', 'in:waiver,discount,write_off,goodwill,fx_adjustment,manual_debit,manual_credit'],
            'direction' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'schedule_id' => ['nullable', 'exists:schedules,id'],
            'effective_on' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    protected function passedValidation(): void
    {
        $this->merge([
            'amount_minor' => money_major_to_minor($this->input('amount')),
        ]);
    }
}
