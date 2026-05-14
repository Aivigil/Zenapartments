<?php

namespace App\Http\Controllers\Payments;

use App\Enums\PaymentChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\PaymentRequest;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\AuditEventWriter;
use App\Services\PaymentAllocator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentsController extends Controller
{
    public function __construct(private PaymentAllocator $allocator)
    {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Payment::class);

        $query = Payment::query()
            ->with(['client:id,code,full_name', 'booking:id,code']);

        if ($request->filled('q')) {
            $q = trim((string) $request->string('q'));
            $query->where(function ($w) use ($q) {
                $w->where('code', 'like', "%{$q}%")
                  ->orWhere('bank_reference', 'like', "%{$q}%")
                  ->orWhereHas('client', fn ($c) => $c->where('full_name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"));
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('channel')) {
            $query->where('channel', $request->string('channel'));
        }

        $payments = $query->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($p) => [
                'id' => $p->id,
                'code' => $p->code,
                'client' => $p->client ? ['id' => $p->client->id, 'code' => $p->client->code, 'full_name' => $p->client->full_name] : null,
                'booking_code' => $p->booking?->code,
                'received_at' => $p->received_at?->format('Y-m-d'),
                'channel' => $p->channel->value,
                'channel_label' => $p->channel->label(),
                'amount_minor' => $p->amount_minor,
                'currency' => $p->currency,
                'pkr_amount_minor' => $p->pkr_amount_minor,
                'bank_reference' => $p->bank_reference,
                'status' => $p->status->value,
            ]);

        return Inertia::render('Payments/Index', [
            'payments' => $payments,
            'filters' => $request->only(['q', 'status', 'channel']),
            'lookups' => [
                'statuses' => [
                    ['value' => 'posted', 'label' => 'Posted'],
                    ['value' => 'reversed', 'label' => 'Reversed'],
                ],
                'channels' => collect(PaymentChannel::cases())->map(fn ($c) => [
                    'value' => $c->value, 'label' => $c->label(),
                ]),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Payment::class);

        $preselectBooking = null;
        if ($request->filled('booking_id')) {
            $b = Booking::with('client:id,code,full_name')->find($request->integer('booking_id'));
            if ($b) {
                $preselectBooking = [
                    'id' => $b->id,
                    'code' => $b->code,
                    'client' => $b->client ? ['id' => $b->client->id, 'full_name' => $b->client->full_name] : null,
                    'currency' => $b->currency,
                    'outstanding_minor' => $b->outstandingMinor(),
                ];
            }
        }

        return Inertia::render('Payments/Form', [
            'preselect' => $preselectBooking,
            'lookups' => [
                'channels' => collect(PaymentChannel::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()]),
                'bookings' => Booking::with('client:id,full_name')
                    ->where('status', 'active')
                    ->orderByDesc('booking_date')
                    ->limit(200)
                    ->get(['id', 'code', 'client_id', 'currency'])
                    ->map(fn ($b) => [
                        'id' => $b->id,
                        'code' => $b->code,
                        'client_name' => $b->client?->full_name,
                        'currency' => $b->currency,
                    ]),
                'currencies' => config('app.currency.accepted'),
            ],
        ]);
    }

    public function store(PaymentRequest $request): RedirectResponse
    {
        Gate::authorize('create', Payment::class);

        $booking = Booking::findOrFail($request->integer('booking_id'));

        $payment = DB::transaction(function () use ($request, $booking) {
            $payment = Payment::create([
                'client_id' => $booking->client_id,
                'booking_id' => $booking->id,
                'channel' => $request->string('channel'),
                'amount_minor' => $request->input('amount_minor'),
                'currency' => $request->string('currency'),
                'fx_rate' => $request->input('fx_rate'),
                'pkr_amount_minor' => $request->input('pkr_amount_minor'),
                'received_at' => $request->date('received_at'),
                'bank_account' => $request->input('bank_account'),
                'bank_reference' => $request->input('bank_reference'),
                'posted_by' => $request->user()->id,
                'status' => 'posted',
                'notes' => $request->input('notes'),
            ]);
            $payment->code = generate_code('ZR-P-', $payment->id, 6);
            $payment->save();

            // Auto-allocate FIFO against open schedules
            $this->allocator->allocate($payment);

            return $payment;
        });

        AuditEventWriter::record(
            event: 'payment.posted',
            subject: $payment,
            after: [
                'booking_id' => $payment->booking_id,
                'amount_minor' => $payment->amount_minor,
                'pkr_amount_minor' => $payment->pkr_amount_minor,
                'channel' => $payment->channel->value,
                'bank_reference' => $payment->bank_reference,
            ],
        );

        return redirect()
            ->route('payments.show', $payment)
            ->with('success', "Payment {$payment->code} recorded and allocated.");
    }

    public function show(Payment $payment): Response
    {
        Gate::authorize('view', $payment);

        $payment->load([
            'client:id,code,full_name',
            'booking:id,code',
            'allocations.schedule:id,sequence_no,label,due_date,amount_minor',
            'postedBy:id,name',
        ]);

        return Inertia::render('Payments/Show', [
            'payment' => [
                'id' => $payment->id,
                'code' => $payment->code,
                'status' => $payment->status->value,
                'received_at' => $payment->received_at?->format('Y-m-d'),
                'channel' => $payment->channel->value,
                'channel_label' => $payment->channel->label(),
                'amount_minor' => $payment->amount_minor,
                'currency' => $payment->currency,
                'fx_rate' => $payment->fx_rate,
                'pkr_amount_minor' => $payment->pkr_amount_minor,
                'bank_account' => $payment->bank_account,
                'bank_reference' => $payment->bank_reference,
                'reversed_on' => $payment->reversed_on?->format('Y-m-d'),
                'reversal_reason' => $payment->reversal_reason,
                'notes' => $payment->notes,
                'client' => $payment->client,
                'booking_code' => $payment->booking?->code,
                'booking_id' => $payment->booking_id,
                'posted_by' => $payment->postedBy?->name,
                'allocations' => $payment->allocations->map(fn ($a) => [
                    'id' => $a->id,
                    'schedule_label' => $a->schedule?->label,
                    'schedule_seq' => $a->schedule?->sequence_no,
                    'schedule_due_date' => $a->schedule?->due_date?->format('Y-m-d'),
                    'amount_minor' => $a->amount_minor,
                    'currency' => $a->currency,
                ]),
            ],
            'can' => [
                'reverse' => request()->user()->can('payments.reverse'),
            ],
        ]);
    }

    /**
     * Reverse a posted payment. Soft action — flips status to 'reversed' and
     * unwinds allocations.
     */
    public function destroy(Request $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('reverse', $payment);

        if ($payment->status->value === 'reversed') {
            return back()->with('error', 'Payment is already reversed.');
        }

        $reason = $request->input('reason') ?: 'No reason provided.';
        $before = ['status' => $payment->status->value];

        DB::transaction(function () use ($payment, $reason, $request) {
            $this->allocator->unallocate($payment);
            $payment->update([
                'status' => 'reversed',
                'reversed_on' => now()->toDateString(),
                'reversal_reason' => $reason,
                'reversed_by' => $request->user()->id,
            ]);
        });

        AuditEventWriter::record(
            event: 'payment.reversed',
            subject: $payment,
            before: $before,
            after: ['status' => 'reversed'],
            reason: $reason,
        );

        return redirect()
            ->route('payments.show', $payment)
            ->with('success', "Payment {$payment->code} reversed.");
    }
}
