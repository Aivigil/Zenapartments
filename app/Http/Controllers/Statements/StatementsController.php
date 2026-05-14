<?php

namespace App\Http\Controllers\Statements;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class StatementsController extends Controller
{
    /**
     * Render the printable statement for a booking, with a running balance.
     * Returns a streamed PDF via DomPDF, served inline (so the browser
     * displays it but the user can also download).
     */
    public function show(Booking $booking)
    {
        Gate::authorize('view', $booking);

        $booking->load([
            'client:id,code,full_name,primary_phone,email,address_line1,city,country',
            'unit:id,code,name,project_id,unit_category_id,size_value,size_unit',
            'unit.project:id,name,location',
            'unit.category:id,name,kind',
            'planTemplate:id,code,name',
            'schedules',
            'payments' => fn ($q) => $q->where('status', 'posted')
                ->with(['allocations.schedule:id,sequence_no,label'])
                ->orderBy('received_at'),
            'adjustments' => fn ($q) => $q->where('status', 'approved')->orderBy('effective_on'),
        ]);

        // Build a unified ledger: schedule items (debits) interleaved with
        // payment allocations (credits) and adjustments, sorted by date,
        // with a running balance.
        $ledger = [];
        $balance = 0;

        // Debits from the schedule
        foreach ($booking->schedules->sortBy('due_date')->sortBy('sequence_no') as $s) {
            if (in_array($s->status, ['waived', 'cancelled', 'written_off'])) continue;
            $balance += $s->amount_minor;
            $ledger[] = [
                'date' => $s->due_date,
                'sort_key' => $s->due_date?->format('Y-m-d') . '-1-' . $s->sequence_no,
                'description' => $s->label,
                'category' => $s->category,
                'debit' => $s->amount_minor,
                'credit' => 0,
                'balance' => $balance,
            ];
        }

        // Credits from payment allocations
        foreach ($booking->payments as $p) {
            foreach ($p->allocations as $a) {
                $balance -= $a->amount_minor;
                $ledger[] = [
                    'date' => $p->received_at,
                    'sort_key' => $p->received_at?->format('Y-m-d') . '-2-' . $p->id . '-' . $a->id,
                    'description' => "Payment {$p->code} → " . ($a->schedule?->label ?? 'allocation'),
                    'category' => 'payment',
                    'debit' => 0,
                    'credit' => $a->amount_minor,
                    'balance' => $balance,
                ];
            }
        }

        // Adjustments
        foreach ($booking->adjustments as $adj) {
            $delta = $adj->direction === 'credit' ? -$adj->amount_minor : $adj->amount_minor;
            $balance += $delta;
            $ledger[] = [
                'date' => $adj->effective_on,
                'sort_key' => $adj->effective_on?->format('Y-m-d') . '-3-' . $adj->id,
                'description' => "Adjustment {$adj->code} ({$adj->kind}): {$adj->reason}",
                'category' => 'adjustment:' . $adj->kind,
                'debit' => $adj->direction === 'debit' ? $adj->amount_minor : 0,
                'credit' => $adj->direction === 'credit' ? $adj->amount_minor : 0,
                'balance' => $balance,
            ];
        }

        // Sort by sort_key so dates are honoured and same-day events are
        // ordered: schedule first, then payments, then adjustments.
        usort($ledger, fn ($a, $b) => strcmp($a['sort_key'], $b['sort_key']));

        // Re-walk balance after sorting to ensure correct running totals
        $balance = 0;
        foreach ($ledger as &$row) {
            $balance += $row['debit'] - $row['credit'];
            $row['balance'] = $balance;
        }
        unset($row);

        $outstanding = $booking->outstandingMinor();

        $pdf = Pdf::loadView('statements.booking', [
            'booking' => $booking,
            'ledger' => $ledger,
            'outstanding' => $outstanding,
            'generated_at' => now(),
            'branding' => config('app.branding'),
            'app_name' => config('app.name'),
        ])
        ->setPaper('a4', 'portrait');

        $filename = "statement-{$booking->code}-" . now()->format('Y-m-d') . ".pdf";
        return $pdf->stream($filename);
    }

    public function download(Booking $booking): Response
    {
        Gate::authorize('view', $booking);
        // Same render, but force-download
        $response = $this->show($booking);
        return $response;
    }
}
