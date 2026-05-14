<?php

namespace App\Http\Controllers\Bookings;

use App\Enums\UnitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bookings\BookingRequest;
use App\Models\Booking;
use App\Models\Client;
use App\Models\PlanTemplate;
use App\Models\Unit;
use App\Services\AuditEventWriter;
use App\Services\PlanInstantiator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BookingsController extends Controller
{
    public function __construct(private PlanInstantiator $planInstantiator)
    {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Booking::class);

        $query = Booking::query()
            ->with(['client:id,code,full_name', 'unit:id,code,name', 'planTemplate:id,code,name']);

        if ($request->filled('q')) {
            $q = trim((string) $request->string('q'));
            $query->where(function ($w) use ($q) {
                $w->where('code', 'like', "%{$q}%")
                  ->orWhereHas('client', fn ($c) => $c->where('full_name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"))
                  ->orWhereHas('unit', fn ($u) => $u->where('code', 'like', "%{$q}%"));
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $bookings = $query->orderByDesc('booking_date')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($b) => [
                'id' => $b->id,
                'code' => $b->code,
                'client' => $b->client ? ['id' => $b->client->id, 'code' => $b->client->code, 'full_name' => $b->client->full_name] : null,
                'unit' => $b->unit ? ['id' => $b->unit->id, 'code' => $b->unit->code, 'name' => $b->unit->name] : null,
                'plan' => $b->planTemplate ? ['code' => $b->planTemplate->code, 'name' => $b->planTemplate->name] : null,
                'booking_date' => $b->booking_date?->format('Y-m-d'),
                'total_price_minor' => $b->total_price_minor,
                'down_payment_minor' => $b->down_payment_minor,
                'currency' => $b->currency,
                'status' => $b->status,
            ]);

        return Inertia::render('Bookings/Index', [
            'bookings' => $bookings,
            'filters' => $request->only(['q', 'status']),
            'lookups' => [
                'statuses' => [
                    ['value' => 'active',    'label' => 'Active'],
                    ['value' => 'completed', 'label' => 'Completed'],
                    ['value' => 'cancelled', 'label' => 'Cancelled'],
                ],
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Booking::class);

        return Inertia::render('Bookings/Form', [
            'booking' => null,
            'lookups' => $this->lookups($request),
        ]);
    }

    public function store(BookingRequest $request): RedirectResponse
    {
        Gate::authorize('create', Booking::class);

        $unit = Unit::lockForUpdate()->findOrFail($request->integer('unit_id'));

        if (! in_array($unit->status->value ?? $unit->status, ['available', 'blocked'], true)) {
            return back()->withErrors([
                'unit_id' => "Unit {$unit->code} is not available (status: {$unit->status->value}).",
            ])->withInput();
        }

        $data = $request->validated();
        $data['total_price_minor'] = $request->input('total_price_minor');
        $data['down_payment_minor'] = $request->input('down_payment_minor') ?? 0;
        unset($data['total_price'], $data['down_payment']);

        $booking = DB::transaction(function () use ($data, $unit) {
            $booking = Booking::create([
                ...$data,
                'status' => 'active',
            ]);
            // Auto-code
            $booking->code = generate_code('ZR-B-', $booking->id, 5);
            $booking->save();

            // Generate schedule
            $this->planInstantiator->generate($booking);

            // Flip unit to sold
            $unit->update(['status' => UnitStatus::Sold->value]);

            return $booking;
        });

        AuditEventWriter::record(
            event: 'booking.created',
            subject: $booking,
            after: [
                'client_id' => $booking->client_id,
                'unit_id' => $booking->unit_id,
                'plan_template_id' => $booking->plan_template_id,
                'total_price_minor' => $booking->total_price_minor,
                'down_payment_minor' => $booking->down_payment_minor,
            ],
        );

        return redirect()
            ->route('bookings.show', $booking)
            ->with('success', "Booking {$booking->code} created with {$booking->schedules()->count()} schedule rows.");
    }

    public function show(Booking $booking): Response
    {
        Gate::authorize('view', $booking);

        $booking->load([
            'client:id,code,full_name,primary_phone',
            'unit:id,code,name,project_id,unit_category_id',
            'unit.project:id,code,name',
            'unit.category:id,name',
            'planTemplate:id,code,name,installment_count',
            'schedules',
            'payments' => fn ($q) => $q->where('status', 'posted')->orderByDesc('received_at'),
        ]);

        $outstanding = $booking->outstandingMinor();
        $scheduledTotal = (int) $booking->schedules
            ->whereNotIn('status', ['waived', 'written_off', 'cancelled'])
            ->sum('amount_minor');
        $paidTotal = (int) ($scheduledTotal - $outstanding);

        return Inertia::render('Bookings/Show', [
            'booking' => [
                'id' => $booking->id,
                'code' => $booking->code,
                'status' => $booking->status,
                'booking_date' => $booking->booking_date?->format('Y-m-d'),
                'total_price_minor' => $booking->total_price_minor,
                'down_payment_minor' => $booking->down_payment_minor,
                'currency' => $booking->currency,
                'notes' => $booking->notes,
                'client' => $booking->client,
                'unit' => $booking->unit ? [
                    'id' => $booking->unit->id,
                    'code' => $booking->unit->code,
                    'name' => $booking->unit->name,
                    'project_name' => $booking->unit->project?->name,
                    'category_name' => $booking->unit->category?->name,
                ] : null,
                'plan' => $booking->planTemplate ? [
                    'code' => $booking->planTemplate->code,
                    'name' => $booking->planTemplate->name,
                ] : null,
                'totals' => [
                    'scheduled_minor' => $scheduledTotal,
                    'paid_minor' => max(0, $paidTotal),
                    'outstanding_minor' => max(0, $outstanding),
                ],
                'schedules' => $booking->schedules->map(fn ($s) => [
                    'id' => $s->id,
                    'sequence_no' => $s->sequence_no,
                    'due_date' => $s->due_date?->format('Y-m-d'),
                    'amount_minor' => $s->amount_minor,
                    'paid_minor' => $s->paid_minor,
                    'category' => $s->category,
                    'label' => $s->label,
                    'status' => $s->status,
                    'is_overdue' => $s->isOverdue(),
                ]),
                'payments' => $booking->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'code' => $p->code,
                    'received_at' => $p->received_at?->format('Y-m-d'),
                    'channel' => $p->channel->value,
                    'amount_minor' => $p->pkr_amount_minor ?? $p->amount_minor,
                    'currency' => 'PKR',
                    'bank_reference' => $p->bank_reference,
                ]),
            ],
        ]);
    }

    public function destroy(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('cancel', $booking);

        $reason = $request->input('reason', 'No reason provided.');
        $beforeStatus = $booking->status;

        DB::transaction(function () use ($booking, $reason, $request) {
            $booking->update([
                'status' => 'cancelled',
                'cancelled_on' => now()->toDateString(),
                'cancellation_reason' => $reason,
                'cancelled_by' => $request->user()->id,
            ]);
            // Cancel any unpaid schedule rows
            $booking->schedules()
                ->whereIn('status', ['due', 'partially_paid'])
                ->update(['status' => 'cancelled']);

            // Free up the unit
            if ($booking->unit) {
                $booking->unit->update(['status' => UnitStatus::Available->value]);
            }
        });

        AuditEventWriter::record(
            event: 'booking.cancelled',
            subject: $booking,
            before: ['status' => $beforeStatus],
            after: ['status' => 'cancelled'],
            reason: $reason,
        );

        return redirect()
            ->route('bookings.show', $booking)
            ->with('success', "Booking {$booking->code} cancelled.");
    }

    private function lookups(Request $request): array
    {
        // Only show units that are currently available + plan templates that are active
        return [
            'clients' => Client::orderBy('full_name')->get(['id', 'code', 'full_name']),
            'available_units' => Unit::with('project:id,name', 'category:id,name')
                ->where('status', UnitStatus::Available->value)
                ->orderBy('code')
                ->get()
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'code' => $u->code,
                    'name' => $u->name,
                    'project_name' => $u->project?->name,
                    'category_name' => $u->category?->name,
                    'base_price_minor' => $u->base_price_minor,
                    'currency' => $u->currency,
                ]),
            'plan_templates' => PlanTemplate::where('active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'down_payment_bps', 'installment_count']),
            'currencies' => config('app.currency.accepted'),
        ];
    }
}
