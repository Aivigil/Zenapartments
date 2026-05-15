<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Schedule;
use App\Models\Unit;
use App\Enums\UnitStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $today = CarbonImmutable::today();
        $startOfMonth = $today->startOfMonth();

        // --- KPIs ---
        $payTodayMinor = (int) DB::table('payments')
            ->where('status', 'posted')
            ->whereDate('received_at', $today->toDateString())
            ->selectRaw('SUM(COALESCE(pkr_amount_minor, amount_minor)) AS s')
            ->value('s');

        $payMtdMinor = (int) DB::table('payments')
            ->where('status', 'posted')
            ->where('received_at', '>=', $startOfMonth->toDateString())
            ->selectRaw('SUM(COALESCE(pkr_amount_minor, amount_minor)) AS s')
            ->value('s');

        $pay30dMinor = (int) DB::table('payments')
            ->where('status', 'posted')
            ->where('received_at', '>=', $today->subDays(30)->toDateString())
            ->selectRaw('SUM(COALESCE(pkr_amount_minor, amount_minor)) AS s')
            ->value('s');

        $outstandingMinor = (int) Schedule::query()
            ->whereIn('status', ['due', 'partially_paid'])
            ->selectRaw('SUM(amount_minor - paid_minor) AS owed')
            ->value('owed');

        $overdueCount = (int) Schedule::query()
            ->whereIn('status', ['due', 'partially_paid'])
            ->where('due_date', '<', $today->toDateString())
            ->count();

        $overdueMinor = (int) Schedule::query()
            ->whereIn('status', ['due', 'partially_paid'])
            ->where('due_date', '<', $today->toDateString())
            ->selectRaw('SUM(amount_minor - paid_minor) AS owed')
            ->value('owed');

        $totals = [
            'projects' => Project::count(),
            'units' => Unit::count(),
            'units_available' => Unit::where('status', UnitStatus::Available->value)->count(),
            'units_sold' => Unit::where('status', UnitStatus::Sold->value)->count(),
            'bookings_active' => Booking::where('status', 'active')->count(),
            'pay_today_minor' => $payTodayMinor,
            'pay_mtd_minor' => $payMtdMinor,
            'pay_30d_minor' => $pay30dMinor,
            'outstanding_minor' => $outstandingMinor,
            'overdue_count' => $overdueCount,
            'overdue_minor' => $overdueMinor,
        ];

        // --- Sparkline: last 30 days of cash-in ---
        $rawDaily = DB::table('payments')
            ->where('status', 'posted')
            ->where('received_at', '>=', $today->subDays(29)->toDateString())
            ->selectRaw("to_char(received_at, 'YYYY-MM-DD') AS day, SUM(COALESCE(pkr_amount_minor, amount_minor)) AS total_minor")
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $sparkline = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = $today->subDays($i)->toDateString();
            $sparkline[] = [
                'day' => $d,
                'value' => (int) ($rawDaily[$d]->total_minor ?? 0),
            ];
        }

        // --- Recent bookings ---
        $recentBookings = Booking::with('client:id,full_name', 'unit:id,code')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'code' => $b->code,
                'client_name' => $b->client?->full_name,
                'unit_code' => $b->unit?->code,
                'total_minor' => (int) $b->total_price_minor,
                'created_at' => $b->created_at?->diffForHumans(),
            ]);

        // --- Recent payments ---
        $recentPayments = Payment::with('client:id,full_name', 'booking:id,code')
            ->where('status', 'posted')
            ->orderByDesc('received_at')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'code' => $p->code,
                'client_name' => $p->client?->full_name,
                'booking_code' => $p->booking?->code,
                'received_at' => $p->received_at?->format('Y-m-d'),
                'amount_minor' => (int) ($p->pkr_amount_minor ?? $p->amount_minor),
                'channel' => $p->channel,
            ]);

        // --- Activity feed (audit_events, last 12) ---
        $activity = AuditEvent::with('actor:id,name')
            ->orderByDesc('occurred_at')
            ->limit(12)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'event' => $e->event,
                'subject_type' => class_basename((string) $e->subject_type),
                'subject_id' => $e->subject_id,
                'actor' => $e->actor?->name,
                'reason' => $e->reason,
                'occurred_at' => $e->occurred_at?->diffForHumans(),
            ]);

        return Inertia::render('Dashboard', [
            'totals' => $totals,
            'sparkline' => $sparkline,
            'recent_bookings' => $recentBookings,
            'recent_payments' => $recentPayments,
            'activity' => $activity,
        ]);
    }
}
