<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bookings.view');
    }

    public function view(User $user, Booking $booking): bool
    {
        if ($user->isClient()) {
            return $user->client_id === $booking->client_id;
        }
        return $user->can('bookings.view');
    }

    public function create(User $user): bool
    {
        return $user->can('bookings.manage');
    }

    public function update(User $user, Booking $booking): bool
    {
        return $user->can('bookings.manage');
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $user->can('bookings.cancel');
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $user->can('bookings.manage');
    }
}
