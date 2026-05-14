<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function collections(Request $request): Response
    {
        $request->user()->can('reports.view') || abort(403);

        $today = CarbonImmutable::today();

        // ============= Aging buckets =============
        // For each open schedule item, compute days_overdue (negative = future).
        // Sum amount_minor − paid_minor into the right bucket.

        $buckets = [
            ['key' => 'future',  'label' => 'Upcoming',     'tone' => 'sky',     'min' => null, 'max' => 0],
            ['key' => 'current', 'label' => 'Due today',    'tone' => 'slate',   'min' => 0,    'max' => 1],
            ['key' => 'b1_30',   'label' => '1–30 days',    'tone' => 'amber',   'min' => 1,    'max' => 31],
            ['key' => 'b31_60',  'label' => '31–60 days',   'tone' => 'orange',  'min' => 31,   'max' => 61],
            ['key' => 'b61_90',  'label' => '61–90 days',   'tone' => 'red',     'min' => 61,   'max' => 91],
            ['key' => 'b90p',    'label' => '90+ days',     'tone' => 'red',     'min' => 91,   'max' => null],
        ];

        $openSchedules = Schedule::query()
            ->whereIn('status', ['due', 'partially_paid'])
            ->selectRaw('due_date, SUM(amount_minor - paid_minor) AS owed_minor, COUNT(*) AS cnt')
            ->groupBy('due_date')
            ->get();

        $bucketResults = collect($buckets)->map(fn ($b) => array_merge($b, ['owed_minor' => 0, 'count' => 0]))->keyBy('key');

        foreach ($openSchedules as $row) {
            $daysOverdue = $today->diffInDays(CarbonImmutable::parse($row->due_date), false) * -1;
            // future: dueDate > today → daysOverdue negative
            // current/overdue: daysOverdue >= 0

            $matched = null;
            foreach ($buckets as $b) {
                if ($daysOverdue < 0 && $b['key'] === 'future') { $matched = 'future'; break; }
                if ($b['min'] === null) continue;
                if ($b['max'] === null) { if ($daysOverdue >= $b['min']) { $matched = $b['key']; break; } continue; }
                if ($daysOverdue >= $b['min'] && $daysOverdue < $b['max']) { $matched = $b['key']; break; }
            }
            if ($matched) {
                $bucketResults[$matched]['owed_minor'] += (int) $row->owed_minor;
                $bucketResults[$matched]['count'] += (int) $row->cnt;
            }
        }

        $bucketArray = $bucketResults->values()->toArray();
        $totalOwedOverdue = collect($bucketArray)->filter(fn ($b) => $b['key'] !== 'future')->sum('owed_minor');
        $totalOwedAll = collect($bucketArray)->sum('owed_minor');

        // ============= Top overdue clients =============
        $overdueByClient = DB::table('schedules')
            ->join('bookings', 'bookings.id', '=', 'schedules.booking_id')
            ->join('clients', 'clients.id', '=', 'bookings.client_id')
            ->whereIn('schedules.status', ['due', 'partially_paid'])
            ->where('schedules.due_date', '<', $today->toDateString())
            ->selectRaw('
                clients.id        AS client_id,
                clients.code      AS client_code,
                clients.full_name AS client_name,
                clients.primary_phone AS phone,
                SUM(schedules.amount_minor - schedules.paid_minor) AS overdue_minor,
                COUNT(*)          AS overdue_items,
                MIN(schedules.due_date) AS oldest_due
            ')
            ->groupBy('clients.id', 'clients.code', 'clients.full_name', 'clients.primary_phone')
            ->orderByDesc('overdue_minor')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'client_id' => $r->client_id,
                'client_code' => $r->client_code,
                'client_name' => $r->client_name,
                'phone' => $r->phone,
                'overdue_minor' => (int) $r->overdue_minor,
                'overdue_items' => (int) $r->overdue_items,
                'oldest_due' => $r->oldest_due,
                'days_overdue' => (int) $today->diffInDays(CarbonImmutable::parse($r->oldest_due), false) * -1,
            ]);

        // ============= Last 12 months cash-in =============
        $startMonth = $today->subMonthsNoOverflow(11)->startOfMonth();
        $cashIn = DB::table('payments')
            ->where('status', 'posted')
            ->where('received_at', '>=', $startMonth->toDateString())
            ->selectRaw("to_char(received_at, 'YYYY-MM') AS month, channel, SUM(COALESCE(pkr_amount_minor, amount_minor)) AS total_minor")
            ->groupBy('month', 'channel')
            ->orderBy('month')
            ->get();

        // Build month-bucket map
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $today->subMonthsNoOverflow(11 - $i);
            $key = $m->format('Y-m');
            $months[$key] = [
                'month' => $key,
                'label' => $m->format('M Y'),
                'total_minor' => 0,
                'by_channel' => [],
            ];
        }
        foreach ($cashIn as $row) {
            if (!isset($months[$row->month])) continue;
            $months[$row->month]['total_minor'] += (int) $row->total_minor;
            $months[$row->month]['by_channel'][$row->channel] = (int) $row->total_minor;
        }
        $monthsArray = array_values($months);
        $cashInTotal12mo = collect($monthsArray)->sum('total_minor');
        $cashInMax = collect($monthsArray)->max('total_minor') ?: 1;

        // ============= Booking pipeline =============
        $bookings = [
            'active' => Booking::where('status', 'active')->count(),
            'completed' => Booking::where('status', 'completed')->count(),
            'cancelled' => Booking::where('status', 'cancelled')->count(),
        ];

        return Inertia::render('Reports/Collections', [
            'today' => $today->format('Y-m-d'),
            'buckets' => $bucketArray,
            'totals' => [
                'overdue_minor' => $totalOwedOverdue,
                'all_open_minor' => $totalOwedAll,
                'cash_in_12mo' => $cashInTotal12mo,
            ],
            'top_overdue' => $overdueByClient,
            'cash_in' => [
                'months' => $monthsArray,
                'max' => $cashInMax,
            ],
            'bookings' => $bookings,
        ]);
    }
}
