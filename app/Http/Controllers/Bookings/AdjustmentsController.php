<?php

namespace App\Http\Controllers\Bookings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bookings\AdjustmentRequest;
use App\Models\Adjustment;
use App\Models\Booking;
use App\Services\AuditEventWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdjustmentsController extends Controller
{
    public function store(AdjustmentRequest $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('create', Adjustment::class);

        $adjustment = DB::transaction(function () use ($request, $booking) {
            $data = $request->validated();
            $data['booking_id'] = $booking->id;
            $data['amount_minor'] = $request->input('amount_minor');
            $data['requested_by'] = $request->user()->id;
            // Auto-approve if the requester also has approve permission (finance_admin).
            // Otherwise stay 'pending' for a separate approver.
            $data['status'] = $request->user()->can('adjustments.approve') ? 'approved' : 'pending';
            if ($data['status'] === 'approved') {
                $data['approved_by'] = $request->user()->id;
                $data['approved_at'] = now();
            }
            unset($data['amount']);

            $adjustment = Adjustment::create($data);
            $adjustment->code = generate_code('ZR-A-', $adjustment->id, 5);
            $adjustment->save();

            // If applied to a specific schedule line and it's a credit waiver,
            // mark the schedule as waived so it stops appearing in outstanding.
            if ($adjustment->status === 'approved'
                && $adjustment->kind === 'waiver'
                && $adjustment->direction === 'credit'
                && $adjustment->schedule_id) {
                $booking->schedules()->whereKey($adjustment->schedule_id)
                    ->update(['status' => 'waived']);
            }

            return $adjustment;
        });

        AuditEventWriter::record(
            event: 'adjustment.posted',
            subject: $adjustment,
            after: [
                'booking_id' => $adjustment->booking_id,
                'kind' => $adjustment->kind,
                'direction' => $adjustment->direction,
                'amount_minor' => $adjustment->amount_minor,
                'schedule_id' => $adjustment->schedule_id,
                'status' => $adjustment->status,
            ],
            reason: $adjustment->reason,
        );

        return back()->with('success', "Adjustment {$adjustment->code} posted ({$adjustment->status}).");
    }

    public function destroy(Booking $booking, Adjustment $adjustment): RedirectResponse
    {
        Gate::authorize('delete', $adjustment);
        abort_unless($adjustment->booking_id === $booking->id, 404);

        $before = ['status' => $adjustment->status];

        DB::transaction(function () use ($adjustment) {
            $adjustment->update(['status' => 'reversed']);
            // If we waived a schedule, restore it
            if ($adjustment->kind === 'waiver' && $adjustment->schedule_id) {
                $adjustment->booking->schedules()
                    ->whereKey($adjustment->schedule_id)
                    ->update(['status' => 'due']);
            }
        });

        AuditEventWriter::record(
            event: 'adjustment.reversed',
            subject: $adjustment,
            before: $before,
            after: ['status' => 'reversed'],
        );

        return back()->with('success', "Adjustment {$adjustment->code} reversed.");
    }
}
