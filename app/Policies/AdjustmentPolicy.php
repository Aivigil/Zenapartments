<?php

namespace App\Policies;

use App\Models\Adjustment;
use App\Models\User;

class AdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('adjustments.request') || $user->can('adjustments.approve');
    }

    public function create(User $user): bool
    {
        return $user->can('adjustments.request');
    }

    public function approve(User $user, Adjustment $adjustment): bool
    {
        return $user->can('adjustments.approve');
    }

    public function delete(User $user, Adjustment $adjustment): bool
    {
        return $user->can('adjustments.approve');
    }
}
