<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientPortalToken;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClientPortalController extends Controller
{
    /**
     * Resolve a portal token to a Client, recording the access, or 404.
     */
    private function resolveToken(string $token, Request $request): ClientPortalToken
    {
        $portalToken = ClientPortalToken::with('client', 'booking')
            ->where('token', $token)
            ->first();

        if (! $portalToken || ! $portalToken->isActive()) {
            abort(410, 'This link has been revoked or has expired.');
        }

        $portalToken->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $request->ip(),
            'use_count' => $portalToken->use_count + 1,
        ])->save();

        return $portalToken;
    }

    public function dashboard(string $token, Request $request): Response
    {
        $portalToken = $this->resolveToken($token, $request);
        $client = $portalToken->client;

        $bookings = $client->bookings()
            ->with(['unit:id,code,name,unit_category_id', 'unit.category:id,name'])
            ->when($portalToken->booking_id, fn ($q) => $q->where('id', $portalToken->booking_id))
            ->orderBy('id')
            ->get()
            ->map(function ($b) {
                $scheduled = (int) $b->schedules()
                    ->whereNotIn('status', ['waived', 'written_off', 'cancelled'])
                    ->sum('amount_minor');
                $outstanding = $b->outstandingMinor();
                $paid = max(0, $scheduled - $outstanding);
                $pctPaid = $scheduled > 0 ? round(($paid / $scheduled) * 100, 1) : 0;

                $nextDue = $b->schedules()
                    ->whereIn('status', ['due', 'partially_paid'])
                    ->where('due_date', '>=', today())
                    ->orderBy('due_date')
                    ->first();

                $overdueItems = $b->schedules()
                    ->whereIn('status', ['due', 'partially_paid'])
                    ->where('due_date', '<', today())
                    ->count();

                return [
                    'id' => $b->id,
                    'code' => $b->code,
                    'status' => $b->status,
                    'unit_code' => $b->unit?->code,
                    'unit_name' => $b->unit?->name,
                    'unit_category' => $b->unit?->category?->name,
                    'total_minor' => (int) $b->total_price_minor,
                    'paid_minor' => $paid,
                    'outstanding_minor' => $outstanding,
                    'pct_paid' => $pctPaid,
                    'overdue_items' => $overdueItems,
                    'next_due_date' => $nextDue?->due_date?->format('Y-m-d'),
                    'next_due_label' => $nextDue?->label,
                    'next_due_minor' => $nextDue ? ($nextDue->amount_minor - $nextDue->paid_minor) : null,
                ];
            });

        // Recent payments across the client's bookings
        $recentPayments = $client->payments()
            ->where('status', 'posted')
            ->orderByDesc('received_at')
            ->limit(8)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'code' => $p->code,
                'received_at' => $p->received_at?->format('Y-m-d'),
                'amount_minor' => (int) ($p->pkr_amount_minor ?? $p->amount_minor),
                'channel' => $p->channel,
                'reference' => $p->reference,
            ]);

        return Inertia::render('Client/Dashboard', [
            'client' => [
                'code' => $client->code,
                'full_name' => $client->full_name,
                'primary_phone' => $client->primary_phone,
                'email' => $client->email,
            ],
            'bookings' => $bookings,
            'recent_payments' => $recentPayments,
            'totals' => [
                'contract_total' => $bookings->sum('total_minor'),
                'paid_total' => $bookings->sum('paid_minor'),
                'outstanding_total' => $bookings->sum('outstanding_minor'),
            ],
            'token' => $token,
            'company' => [
                'name' => config('app.branding.company', config('app.name')),
                'phone' => config('app.branding.phone', '+92 21 1234567'),
                'whatsapp' => config('app.branding.whatsapp', '+92 300 1234567'),
                'email' => config('app.branding.email', 'sales@zenretreatspk.com'),
            ],
        ]);
    }

    /**
     * Render the booking statement PDF for the client (read-only access via token).
     */
    public function statement(string $token, int $bookingId, Request $request)
    {
        $portalToken = $this->resolveToken($token, $request);
        $client = $portalToken->client;

        $booking = $client->bookings()->findOrFail($bookingId);

        // If the token was scoped to a single booking, enforce that
        if ($portalToken->booking_id && $portalToken->booking_id !== $booking->id) {
            abort(403, 'This link does not have access to that booking.');
        }

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

        // Build ledger — reuse same logic as StatementsController
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

        $pdf = Pdf::loadView('statements.booking', [
            'booking' => $booking,
            'ledger' => $ledger,
            'outstanding' => $booking->outstandingMinor(),
            'generated_at' => now(),
            'branding' => config('app.branding'),
            'app_name' => config('app.name'),
            'is_client_copy' => true,
        ])
        ->setPaper('a4', 'portrait');

        $filename = "statement-{$booking->code}-" . now()->format('Y-m-d') . ".pdf";
        return $pdf->stream($filename);
    }
}
