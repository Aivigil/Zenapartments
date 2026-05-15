<?php

namespace App\Http\Controllers\Bookings;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Notifications\NotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use ZipArchive;

class BulkActionsController extends Controller
{
    public function __construct(private NotificationService $notifier)
    {
    }

    /**
     * Queue reminder notifications for a batch of bookings.
     * For each booking we pick the most-overdue open schedule item (or the
     * nearest upcoming one if none overdue), and fire the chosen template.
     */
    public function reminders(Request $request): RedirectResponse
    {
        $request->user()->can('notifications.send') || abort(403);

        $data = $request->validate([
            'booking_ids' => ['required', 'array', 'min:1', 'max:200'],
            'booking_ids.*' => ['integer', 'exists:bookings,id'],
            'template_code' => ['required', 'string'],
            'channel' => ['required', 'in:email,sms,whatsapp'],
        ]);

        $bookings = Booking::with('client', 'schedules')
            ->whereIn('id', $data['booking_ids'])
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($bookings as $booking) {
            if (! $booking->client) { $skipped++; continue; }

            // Pick the worst-overdue schedule, fall back to next-due
            $overdue = $booking->schedules
                ->whereIn('status', ['due', 'partially_paid'])
                ->where('due_date', '<', today())
                ->sortBy('due_date')
                ->first();
            $nextDue = $overdue ?: $booking->schedules
                ->whereIn('status', ['due', 'partially_paid'])
                ->where('due_date', '>=', today())
                ->sortBy('due_date')
                ->first();
            if (! $nextDue) { $skipped++; continue; }

            $owed = $nextDue->amount_minor - $nextDue->paid_minor;
            $log = $this->notifier->send($data['template_code'], $booking->client, [
                'channel' => $data['channel'],
                'booking_id' => $booking->id,
                'schedule_id' => $nextDue->id,
                'data' => [
                    'amount' => money_format_pkr($owed, false),
                    'amount_with_symbol' => money_format_pkr($owed),
                    'due_date' => $nextDue->due_date?->format('d M Y'),
                    'booking_code' => $booking->code,
                    'item' => $nextDue->label,
                ],
            ]);
            if ($log) $sent++; else $skipped++;
        }

        return back()->with('success', "Bulk reminders: {$sent} queued, {$skipped} skipped.");
    }

    /**
     * Generate statement PDFs for selected bookings, bundle as zip, stream back.
     */
    public function statementsZip(Request $request): Response
    {
        Gate::authorize('viewAny', Booking::class);

        $data = $request->validate([
            'booking_ids' => ['required', 'array', 'min:1', 'max:50'],
            'booking_ids.*' => ['integer', 'exists:bookings,id'],
        ]);

        $bookings = Booking::with([
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
        ])->whereIn('id', $data['booking_ids'])->get();

        $tmp = tempnam(sys_get_temp_dir(), 'stmts-') . '.zip';
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::CREATE);

        foreach ($bookings as $booking) {
            $ledger = $this->buildLedger($booking);
            $pdf = Pdf::loadView('statements.booking', [
                'booking' => $booking,
                'ledger' => $ledger,
                'outstanding' => $booking->outstandingMinor(),
                'generated_at' => now(),
                'branding' => config('app.branding'),
                'app_name' => config('app.name'),
            ])->setPaper('a4', 'portrait');

            $filename = "statement-{$booking->code}.pdf";
            $zip->addFromString($filename, $pdf->output());
        }
        $zip->close();

        $zipName = 'statements-' . now()->format('Y-m-d-His') . '.zip';
        return response()->download($tmp, $zipName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Reassign a salesperson on a batch of bookings.
     */
    public function reassignSalesperson(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', Booking::class);

        $data = $request->validate([
            'booking_ids' => ['required', 'array', 'min:1', 'max:200'],
            'booking_ids.*' => ['integer', 'exists:bookings,id'],
            'salesperson_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $updated = Booking::whereIn('id', $data['booking_ids'])
            ->update(['salesperson_id' => $data['salesperson_id'] ?? null]);

        return back()->with('success', "Reassigned {$updated} bookings.");
    }

    private function buildLedger(Booking $booking): array
    {
        $ledger = [];
        $balance = 0;
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
        usort($ledger, fn ($a, $b) => strcmp($a['sort_key'], $b['sort_key']));
        $balance = 0;
        foreach ($ledger as &$row) {
            $balance += $row['debit'] - $row['credit'];
            $row['balance'] = $balance;
        }
        unset($row);
        return $ledger;
    }
}
