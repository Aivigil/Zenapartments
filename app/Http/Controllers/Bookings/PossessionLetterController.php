<?php

namespace App\Http\Controllers\Bookings;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PossessionLetterController extends Controller
{
    /**
     * Generate a possession letter PDF for a booking.
     * Gated on: 100% paid AND no overdue items.
     */
    public function show(Booking $booking): Response
    {
        Gate::authorize('view', $booking);

        $booking->load([
            'client',
            'unit:id,code,name,project_id,unit_category_id,size_value,size_unit',
            'unit.project:id,name,location',
            'unit.category:id,name,kind',
        ]);

        $scheduled = (int) $booking->schedules()
            ->whereNotIn('status', ['waived', 'written_off', 'cancelled'])
            ->sum('amount_minor');
        $outstanding = $booking->outstandingMinor();
        $paid = max(0, $scheduled - $outstanding);
        $pct = $scheduled > 0 ? ($paid / $scheduled) : 0;
        $overdueItems = $booking->schedules()
            ->whereIn('status', ['due', 'partially_paid'])
            ->where('due_date', '<', today())
            ->count();

        // Allow override for ops staff with documents permission
        $forced = request()->boolean('force');
        $canForce = request()->user()?->can('bookings.manage');

        if (! $forced || ! $canForce) {
            abort_unless($pct >= 1.0, 422, 'Cannot issue possession letter: balance still outstanding.');
            abort_unless($overdueItems === 0, 422, 'Cannot issue possession letter: overdue items present.');
        }

        $pdf = Pdf::loadView('possession-letters.booking', [
            'booking' => $booking,
            'paid_minor' => $paid,
            'outstanding_minor' => $outstanding,
            'generated_at' => now(),
            'branding' => config('app.branding'),
            'app_name' => config('app.name'),
        ])->setPaper('a4', 'portrait');

        $filename = "possession-letter-{$booking->code}-" . now()->format('Y-m-d') . ".pdf";
        return $pdf->stream($filename);
    }
}
