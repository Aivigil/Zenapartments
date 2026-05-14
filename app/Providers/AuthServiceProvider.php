<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Enums\RoleName;
use App\Models\Project;
use App\Models\Block;
use App\Models\Unit;
use App\Models\UnitCategory;
use App\Models\Client;
use App\Models\Booking;
use App\Models\Payment;
use App\Policies\ProjectPolicy;
use App\Policies\BlockPolicy;
use App\Policies\UnitPolicy;
use App\Policies\UnitCategoryPolicy;
use App\Policies\ClientPolicy;
use App\Policies\BookingPolicy;
use App\Policies\PaymentPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Project::class => ProjectPolicy::class,
        Block::class => BlockPolicy::class,
        Unit::class => UnitPolicy::class,
        UnitCategory::class => UnitCategoryPolicy::class,
        Client::class => ClientPolicy::class,
        Booking::class => BookingPolicy::class,
        Payment::class => PaymentPolicy::class,
    ];

    public function boot(): void
    {
        // Super admins bypass all permission checks.
        Gate::before(function ($user, $ability) {
            return $user->hasRole(RoleName::SuperAdmin->value) ? true : null;
        });
    }
}
