<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payments.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($user->isClient()) {
            return $user->client_id === $payment->client_id;
        }
        return $user->can('payments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('payments.post');
    }

    public function reverse(User $user, Payment $payment): bool
    {
        return $user->can('payments.reverse');
    }
}
