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
    public function cashFlow(Request $request): Response
    {
        $request->user()->can('reports.view') || abort(403);

        $today = CarbonImmutable::today();
        $startMonth = $today->subMonthsNoOverflow(11)->startOfMonth();

        $byMonth = DB::table('payments')
            ->where('status', 'posted')
            ->where('received_at', '>=', $startMonth->toDateString())
            ->selectRaw("to_char(received_at, 'YYYY-MM') AS month, channel, SUM(COALESCE(pkr_amount_minor, amount_minor)) AS total_minor, COUNT(*) AS cnt")
            ->groupBy('month', 'channel')
            ->orderBy('month')
            ->get();

        // Init 12 month buckets so months with no payments still show
        $months = [];
        $channels = ['bank_transfer', 'cash', 'cheque', 'online_gateway', 'foreign_wire'];
        for ($i = 0; $i < 12; $i++) {
            $m = $today->subMonthsNoOverflow(11 - $i);
            $key = $m->format('Y-m');
            $months[$key] = [
                'month' => $key,
                'label' => $m->format('M Y'),
                'total_minor' => 0,
                'count' => 0,
                'by_channel' => array_fill_keys($channels, 0),
            ];
        }
        foreach ($byMonth as $row) {
            if (!isset($months[$row->month])) continue;
            $months[$row->month]['total_minor'] += (int) $row->total_minor;
            $months[$row->month]['count'] += (int) $row->cnt;
            $months[$row->month]['by_channel'][$row->channel] = (int) $row->total_minor;
        }

        $monthsArr = array_values($months);
        $cashInTotal = collect($monthsArr)->sum('total_minor');
        $cashInMax = collect($monthsArr)->max('total_minor') ?: 1;

        $channelTotals = [];
        foreach ($channels as $c) {
            $channelTotals[$c] = collect($monthsArr)->sum(fn ($m) => $m['by_channel'][$c]);
        }

        return Inertia::render('Reports/CashFlow', [
            'months' => $monthsArr,
            'total_minor' => $cashInTotal,
            'max_minor' => $cashInMax,
            'channel_totals' => $channelTotals,
            'channels' => $channels,
        ]);
    }

    public function bookingSummary(Request $request): Response
    {
        $request->user()->can('reports.view') || abort(403);

        $bookings = \App\Models\Booking::query()
            ->with(['client:id,code,full_name', 'unit:id,code,name', 'unit.category:id,name', 'planTemplate:id,code'])
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->map(function ($b) {
                $scheduled = (int) $b->schedules()
                    ->whereNotIn('status', ['waived', 'written_off', 'cancelled'])
                    ->sum('amount_minor');
                $outstanding = $b->outstandingMinor();
                $paid = max(0, $scheduled - $outstanding);
                $overdueItems = $b->schedules()
                    ->whereIn('status', ['due', 'partially_paid'])
                    ->where('due_date', '<', today())
                    ->count();
                $nextDue = $b->schedules()
                    ->whereIn('status', ['due', 'partially_paid'])
                    ->where('due_date', '>=', today())
                    ->orderBy('due_date')
                    ->first();

                return [
                    'id' => $b->id,
                    'code' => $b->code,
                    'client_id' => $b->client_id,
                    'client_code' => $b->client?->code,
                    'client_name' => $b->client?->full_name,
                    'unit_code' => $b->unit?->code,
                    'unit_category' => $b->unit?->category?->name,
                    'plan_code' => $b->planTemplate?->code,
                    'total_minor' => $b->total_price_minor,
                    'paid_minor' => $paid,
                    'outstanding_minor' => $outstanding,
                    'overdue_items' => $overdueItems,
                    'next_due_date' => $nextDue?->due_date?->format('Y-m-d'),
                    'next_due_amount_minor' => $nextDue ? ($nextDue->amount_minor - $nextDue->paid_minor) : null,
                ];
            });

        return Inertia::render('Reports/BookingSummary', [
            'bookings' => $bookings,
            'totals' => [
                'count' => $bookings->count(),
                'contract_total' => $bookings->sum('total_minor'),
                'paid_total' => $bookings->sum('paid_minor'),
                'outstanding_total' => $bookings->sum('outstanding_minor'),
            ],
        ]);
    }

    /**
     * Sales forecast — expected cash-in for the next N days, grouped weekly.
     * Includes a sanity benchmark (last-month actual collection rate).
     */
    public function forecast(Request $request): Response
    {
        $request->user()->can('reports.view') || abort(403);

        $today = CarbonImmutable::today();
        $horizon = (int) $request->integer('days', 90);
        $horizon = max(30, min(180, $horizon));
        $end = $today->addDays($horizon);

        // Open schedule items in the window
        $items = DB::table('schedules')
            ->join('bookings', 'bookings.id', '=', 'schedules.booking_id')
            ->whereIn('schedules.status', ['due', 'partially_paid'])
            ->whereBetween('schedules.due_date', [$today->toDateString(), $end->toDateString()])
            ->where('bookings.status', 'active')
            ->selectRaw('schedules.due_date, schedules.category, SUM(schedules.amount_minor - schedules.paid_minor) AS owed_minor, COUNT(*) AS cnt')
            ->groupBy('schedules.due_date', 'schedules.category')
            ->orderBy('schedules.due_date')
            ->get();

        // Bucket into weeks
        $weeks = [];
        for ($i = 0; $i * 7 <= $horizon; $i++) {
            $weekStart = $today->addDays($i * 7);
            $weekEnd = min($weekStart->addDays(6)->toDateString(), $end->toDateString());
            $weeks["w{$i}"] = [
                'key' => "w{$i}",
                'label' => $weekStart->format('M j') . ' – ' . CarbonImmutable::parse($weekEnd)->format('M j'),
                'start' => $weekStart->toDateString(),
                'end' => $weekEnd,
                'expected_minor' => 0,
                'count' => 0,
                'by_category' => [],
            ];
        }

        foreach ($items as $row) {
            $due = CarbonImmutable::parse($row->due_date);
            // Carbon 3 returns float from diffInDays — cast to int for intdiv
            $diff = (int) $today->diffInDays($due, false);
            if ($diff < 0) continue;
            $bucketIdx = intdiv($diff, 7);
            $key = "w{$bucketIdx}";
            if (! isset($weeks[$key])) continue;
            $weeks[$key]['expected_minor'] += (int) $row->owed_minor;
            $weeks[$key]['count'] += (int) $row->cnt;
            $weeks[$key]['by_category'][$row->category] = ($weeks[$key]['by_category'][$row->category] ?? 0) + (int) $row->owed_minor;
        }

        $weeksArr = array_values($weeks);
        $totalExpected = collect($weeksArr)->sum('expected_minor');
        $maxWeekly = collect($weeksArr)->max('expected_minor') ?: 1;

        // Benchmark: actual collections last 30 days vs scheduled-due last 30 days
        $actualLast30 = (int) DB::table('payments')
            ->where('status', 'posted')
            ->where('received_at', '>=', $today->subDays(30)->toDateString())
            ->sum(DB::raw('COALESCE(pkr_amount_minor, amount_minor)'));

        $scheduledLast30 = (int) DB::table('schedules')
            ->where('due_date', '>=', $today->subDays(30)->toDateString())
            ->where('due_date', '<', $today->toDateString())
            ->sum('amount_minor');

        $collectionRate = $scheduledLast30 > 0 ? round($actualLast30 / $scheduledLast30, 3) : null;

        return Inertia::render('Reports/Forecast', [
            'today' => $today->format('Y-m-d'),
            'horizon_days' => $horizon,
            'weeks' => $weeksArr,
            'total_expected_minor' => $totalExpected,
            'max_weekly_minor' => $maxWeekly,
            'benchmark' => [
                'actual_last_30_minor' => $actualLast30,
                'scheduled_last_30_minor' => $scheduledLast30,
                'collection_rate' => $collectionRate,
                'adjusted_forecast_minor' => $collectionRate ? (int) round($totalExpected * $collectionRate) : null,
            ],
        ]);
    }

    /**
     * Possession-due tracker. Bookings approaching possession:
     *   - status='active' AND (% paid >= 90% OR final installment in next 60 days).
     * Drives the "who's about to take possession" workflow.
     */
    public function possession(Request $request): Response
    {
        $request->user()->can('reports.view') || abort(403);

        $today = CarbonImmutable::today();
        $windowEnd = $today->addDays(60);

        $bookings = Booking::query()
            ->with(['client:id,code,full_name,primary_phone,email', 'unit:id,code,name,unit_category_id', 'unit.category:id,name'])
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->map(function ($b) use ($today) {
                $scheduled = (int) $b->schedules()
                    ->whereNotIn('status', ['waived', 'written_off', 'cancelled'])
                    ->sum('amount_minor');
                $outstanding = $b->outstandingMinor();
                $paid = max(0, $scheduled - $outstanding);
                $pct = $scheduled > 0 ? round(($paid / $scheduled) * 100, 1) : 0;

                $lastSch = $b->schedules()
                    ->whereNotIn('status', ['waived', 'written_off', 'cancelled'])
                    ->orderByDesc('due_date')
                    ->first();
                $finalDue = $lastSch?->due_date;

                $overdueItems = $b->schedules()
                    ->whereIn('status', ['due', 'partially_paid'])
                    ->where('due_date', '<', $today->toDateString())
                    ->count();
                // .reorder() clears the inherited orderBy('sequence_no') from the
                // Booking::schedules() relation — otherwise Postgres rejects the
                // aggregate query with "must appear in GROUP BY".
                $overdueAmount = (int) $b->schedules()
                    ->whereIn('status', ['due', 'partially_paid'])
                    ->where('due_date', '<', $today->toDateString())
                    ->reorder()
                    ->sum(\DB::raw('amount_minor - paid_minor'));

                return [
                    'booking' => $b,
                    'pct_paid' => $pct,
                    'paid_minor' => $paid,
                    'total_minor' => $scheduled,
                    'outstanding_minor' => $outstanding,
                    'final_due_date' => $finalDue?->format('Y-m-d'),
                    'days_to_final' => $finalDue ? (int) $today->diffInDays(CarbonImmutable::parse($finalDue->format('Y-m-d')), false) : null,
                    'overdue_items' => $overdueItems,
                    'overdue_amount_minor' => $overdueAmount,
                ];
            })
            ->filter(function ($r) use ($windowEnd, $today) {
                if ($r['pct_paid'] >= 90) return true;
                if ($r['final_due_date']) {
                    $fd = CarbonImmutable::parse($r['final_due_date']);
                    if ($fd->greaterThanOrEqualTo($today) && $fd->lessThanOrEqualTo($windowEnd)) return true;
                }
                return false;
            })
            ->sortByDesc('pct_paid')
            ->values()
            ->map(fn ($r) => [
                'id' => $r['booking']->id,
                'code' => $r['booking']->code,
                'client_id' => $r['booking']->client_id,
                'client_code' => $r['booking']->client?->code,
                'client_name' => $r['booking']->client?->full_name,
                'phone' => $r['booking']->client?->primary_phone,
                'email' => $r['booking']->client?->email,
                'unit_code' => $r['booking']->unit?->code,
                'unit_name' => $r['booking']->unit?->name,
                'unit_category' => $r['booking']->unit?->category?->name,
                'total_minor' => $r['total_minor'],
                'paid_minor' => $r['paid_minor'],
                'outstanding_minor' => $r['outstanding_minor'],
                'pct_paid' => $r['pct_paid'],
                'final_due_date' => $r['final_due_date'],
                'days_to_final' => $r['days_to_final'],
                'overdue_items' => $r['overdue_items'],
                'overdue_amount_minor' => $r['overdue_amount_minor'],
                'eligible' => $r['pct_paid'] >= 100 && $r['overdue_items'] === 0,
            ]);

        return Inertia::render('Reports/Possession', [
            'today' => $today->format('Y-m-d'),
            'window_end' => $windowEnd->format('Y-m-d'),
            'bookings' => $bookings,
            'totals' => [
                'count' => $bookings->count(),
                'eligible_count' => $bookings->where('eligible', true)->count(),
                'over_90_count' => $bookings->where('pct_paid', '>=', 90)->count(),
                'outstanding_total' => (int) $bookings->sum('outstanding_minor'),
            ],
        ]);
    }

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

        // Plain array keyed by bucket key — Collections don't support
        // `$col[$k]['nested'] +=` (returns by value, mutation lost / fatal on 8.4).
        $bucketResults = [];
        foreach ($buckets as $b) {
            $bucketResults[$b['key']] = array_merge($b, ['owed_minor' => 0, 'count' => 0]);
        }

        foreach ($openSchedules as $row) {
            // Cast to int — Carbon 3 returns float from diffInDays
            $daysOverdue = (int) ($today->diffInDays(CarbonImmutable::parse($row->due_date), false) * -1);

            $matched = null;
            foreach ($buckets as $b) {
                if ($daysOverdue < 0 && $b['key'] === 'future') { $matched = 'future'; break; }
                if ($b['min'] === null) continue;
                if ($b['max'] === null) { if ($daysOverdue >= $b['min']) { $matched = $b['key']; break; } continue; }
                if ($daysOverdue >= $b['min'] && $daysOverdue < $b['max']) { $matched = $b['key']; break; }
            }
            if ($matched) {
                $bucketResults[$matched]['owed_minor'] += (int) $row->owed_minor;
                $bucketResults[$matched]['count']      += (int) $row->cnt;
            }
        }

        $bucketArray = array_values($bucketResults);
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
