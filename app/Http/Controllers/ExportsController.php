<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportsController extends Controller
{
    public function clients(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Client::class);

        $query = Client::query()->withCount('bookings');

        if ($request->filled('q')) {
            $q = trim((string) $request->string('q'));
            $query->where(function ($w) use ($q) {
                $w->where('full_name', 'ilike', "%{$q}%")
                  ->orWhere('code', 'ilike', "%{$q}%")
                  ->orWhere('primary_phone', 'ilike', "%{$q}%")
                  ->orWhere('email', 'ilike', "%{$q}%");
            });
        }
        if ($request->filled('kyc_status')) {
            $query->where('kyc_status', $request->string('kyc_status'));
        }

        return $this->stream('clients-' . now()->format('Y-m-d-His') . '.csv', function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Code', 'Full name', 'Father/Husband', 'Primary phone', 'Alt phone',
                'Email', 'Address', 'City', 'Country',
                'Country of residence', 'Nationality', 'KYC status',
                'Bookings', 'Created at',
            ]);
            $query->orderBy('full_name')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $c) {
                    fputcsv($out, [
                        $c->code,
                        $c->full_name,
                        $c->father_or_husband_name,
                        $c->primary_phone,
                        $c->alt_phone,
                        $c->email,
                        trim(implode(' ', array_filter([$c->address_line1, $c->address_line2]))),
                        $c->city,
                        $c->country,
                        $c->country_of_residence,
                        $c->nationality,
                        $c->kyc_status,
                        $c->bookings_count,
                        $c->created_at?->format('Y-m-d H:i'),
                    ]);
                }
            });
            fclose($out);
        });
    }

    public function bookings(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Booking::class);

        $query = Booking::query()
            ->with(['client:id,code,full_name,primary_phone', 'unit:id,code,name', 'unit.category:id,name', 'planTemplate:id,code,name']);

        if ($request->filled('q')) {
            $q = trim((string) $request->string('q'));
            $query->where(function ($w) use ($q) {
                $w->where('code', 'ilike', "%{$q}%")
                  ->orWhereHas('client', fn ($c) => $c->where('full_name', 'ilike', "%{$q}%")->orWhere('code', 'ilike', "%{$q}%"))
                  ->orWhereHas('unit', fn ($u) => $u->where('code', 'ilike', "%{$q}%"));
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return $this->stream('bookings-' . now()->format('Y-m-d-His') . '.csv', function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Booking', 'Client code', 'Client name', 'Phone',
                'Unit', 'Unit name', 'Category', 'Plan',
                'Booking date', 'Total (PKR)', 'Paid (PKR)', 'Outstanding (PKR)',
                'Currency', 'Status', 'Notes',
            ]);
            $query->orderBy('id')->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $b) {
                    $scheduled = (int) $b->schedules()
                        ->whereNotIn('status', ['waived', 'written_off', 'cancelled'])
                        ->sum('amount_minor');
                    $outstanding = $b->outstandingMinor();
                    $paid = max(0, $scheduled - $outstanding);
                    fputcsv($out, [
                        $b->code,
                        $b->client?->code,
                        $b->client?->full_name,
                        $b->client?->primary_phone,
                        $b->unit?->code,
                        $b->unit?->name,
                        $b->unit?->category?->name,
                        $b->planTemplate?->code,
                        $b->booking_date?->format('Y-m-d'),
                        number_format($b->total_price_minor / 100, 2, '.', ''),
                        number_format($paid / 100, 2, '.', ''),
                        number_format($outstanding / 100, 2, '.', ''),
                        $b->currency,
                        $b->status,
                        $b->notes,
                    ]);
                }
            });
            fclose($out);
        });
    }

    public function payments(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Payment::class);

        $query = Payment::query()
            ->with(['client:id,code,full_name', 'booking:id,code']);

        if ($request->filled('q')) {
            $q = trim((string) $request->string('q'));
            $query->where(function ($w) use ($q) {
                $w->where('code', 'ilike', "%{$q}%")
                  ->orWhere('reference', 'ilike', "%{$q}%")
                  ->orWhereHas('client', fn ($c) => $c->where('full_name', 'ilike', "%{$q}%")->orWhere('code', 'ilike', "%{$q}%"));
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('channel')) {
            $query->where('channel', $request->string('channel'));
        }
        if ($request->filled('from')) {
            $query->where('received_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('received_at', '<=', $request->date('to'));
        }

        return $this->stream('payments-' . now()->format('Y-m-d-His') . '.csv', function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Payment', 'Received at', 'Client code', 'Client name', 'Booking',
                'Channel', 'Reference', 'Currency', 'Amount', 'PKR amount',
                'Status', 'Reversed at', 'Notes',
            ]);
            $query->orderByDesc('received_at')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $p) {
                    fputcsv($out, [
                        $p->code,
                        $p->received_at?->format('Y-m-d'),
                        $p->client?->code,
                        $p->client?->full_name,
                        $p->booking?->code,
                        $p->channel,
                        $p->reference,
                        $p->currency,
                        number_format($p->amount_minor / 100, 2, '.', ''),
                        $p->pkr_amount_minor !== null ? number_format($p->pkr_amount_minor / 100, 2, '.', '') : '',
                        $p->status,
                        $p->reversed_at?->format('Y-m-d H:i'),
                        $p->notes,
                    ]);
                }
            });
            fclose($out);
        });
    }

    private function stream(string $filename, callable $writer): StreamedResponse
    {
        return response()->streamDownload($writer, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
