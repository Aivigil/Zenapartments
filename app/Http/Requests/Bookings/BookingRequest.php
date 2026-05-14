<?php

namespace App\Http\Requests\Bookings;

use Illuminate\Foundation\Http\FormRequest;

class BookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bookings.manage');
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'plan_template_id' => ['required', 'exists:plan_templates,id'],
            'booking_date' => ['required', 'date'],
            'total_price' => ['required', 'numeric', 'min:0'],
            'down_payment' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'salesperson_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function passedValidation(): void
    {
        $this->merge([
            'total_price_minor' => money_major_to_minor($this->input('total_price')),
            'down_payment_minor' => $this->input('down_payment') !== null
                ? money_major_to_minor($this->input('down_payment'))
                : null,
        ]);
    }
}
