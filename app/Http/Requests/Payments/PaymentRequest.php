<?php

namespace App\Http\Requests\Payments;

use App\Enums\PaymentChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payments.post');
    }

    public function rules(): array
    {
        return [
            'booking_id' => ['required', 'exists:bookings,id'],
            'channel' => ['required', new Enum(PaymentChannel::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'fx_rate' => ['nullable', 'numeric', 'min:0'],
            'received_at' => ['required', 'date'],
            'bank_account' => ['nullable', 'string', 'max:255'],
            'bank_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function passedValidation(): void
    {
        $amount = money_major_to_minor($this->input('amount'));
        $merge = ['amount_minor' => $amount];

        // If foreign currency, derive PKR-equivalent at the FX rate provided
        if ($this->input('currency') !== 'PKR') {
            $rate = (float) ($this->input('fx_rate') ?? 1);
            $merge['pkr_amount_minor'] = (int) round($amount * $rate);
        } else {
            $merge['pkr_amount_minor'] = $amount;
        }
        $this->merge($merge);
    }
}
