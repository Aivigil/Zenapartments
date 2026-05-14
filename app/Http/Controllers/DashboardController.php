<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Unit;
use App\Models\Booking;
use App\Models\Payment;
use App\Enums\UnitStatus;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $totals = [
            'projects' => Project::count(),
            'units' => Unit::count(),
            'units_available' => Unit::where('status', UnitStatus::Available->value)->count(),
            'units_sold' => Unit::where('status', UnitStatus::Sold->value)->count(),
            'bookings_active' => Booking::where('status', 'active')->count(),
            'payments_30d' => Payment::where('received_at', '>=', now()->subDays(30))
                ->where('status', 'posted')
                ->sum('pkr_amount_minor'),
        ];

        return Inertia::render('Dashboard', [
            'totals' => $totals,
        ]);
    }
}
