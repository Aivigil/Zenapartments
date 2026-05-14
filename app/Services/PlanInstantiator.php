<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PlanTemplate;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Generates the concrete Schedule rows for a Booking from its PlanTemplate.
 *
 * Strategy:
 *   1. Down payment due on the booking date (amount = total_price * down_payment_bps).
 *   2. Milestone charges (type=percent) distributed by sort_order across the
 *      plan duration — first milestone at month 1, last at month=installment_count.
 *      Each milestone amount = total_price * milestone.value / 10000 (basis points).
 *   3. Remaining balance (total - down_payment - sum(milestones)) split evenly
 *      across installment_count rows, monthly starting one month after booking.
 *      Last installment carries any rounding remainder so the math closes.
 */
class PlanInstantiator
{
    public function generate(Booking $booking): array
    {
        $template = $booking->planTemplate;
        if (! $template) {
            throw new \InvalidArgumentException("Booking #{$booking->id} has no plan template.");
        }

        $total = (int) $booking->total_price_minor;
        $downPayment = $booking->down_payment_minor
            ?: (int) round($total * (($template->down_payment_bps ?? 0) / 10000));

        $milestones = collect($template->milestone_charges ?? [])
            ->sortBy('sort_order')
            ->values();

        $milestoneTotal = (int) $milestones
            ->where('type', 'percent')
            ->reduce(fn ($carry, $m) => $carry + (int) round($total * ($m['value'] / 10000)), 0);

        // Add fixed-amount milestones (type=fixed) — value is in minor units already.
        $milestoneFixedTotal = (int) $milestones
            ->where('type', 'fixed')
            ->sum('value');

        $installmentBase = $total - $downPayment - $milestoneTotal - $milestoneFixedTotal;
        $installmentCount = max(0, (int) $template->installment_count);

        $perInstallment = $installmentCount > 0
            ? intdiv($installmentBase, $installmentCount)
            : 0;
        $remainder = $installmentBase - ($perInstallment * $installmentCount);

        $bookingDate = CarbonImmutable::parse($booking->booking_date);
        $rows = [];
        $sequence = 1;

        // 1. Down payment
        $rows[] = [
            'sequence_no' => $sequence++,
            'due_date' => $bookingDate->toDateString(),
            'amount_minor' => $downPayment,
            'currency' => $booking->currency,
            'category' => 'down_payment',
            'label' => 'Down payment',
            'status' => 'due',
        ];

        // 2. Milestone charges — spaced across the plan
        $milestoneCount = $milestones->count();
        foreach ($milestones->values() as $idx => $m) {
            $amount = $m['type'] === 'percent'
                ? (int) round($total * ($m['value'] / 10000))
                : (int) $m['value'];

            // Distribute: first milestone at month 1, last at month=installment_count.
            $monthOffset = $milestoneCount === 1
                ? max(1, $installmentCount)
                : (int) round(1 + (($idx) * ($installmentCount - 1) / max(1, $milestoneCount - 1)));

            $rows[] = [
                'sequence_no' => $sequence++,
                'due_date' => $bookingDate->addMonths($monthOffset)->toDateString(),
                'amount_minor' => $amount,
                'currency' => $booking->currency,
                'category' => 'milestone:' . ($m['code'] ?? 'milestone'),
                'label' => $m['label'] ?? ucfirst($m['code'] ?? 'Milestone'),
                'status' => 'due',
            ];
        }

        // 3. Monthly installments
        for ($i = 1; $i <= $installmentCount; $i++) {
            $amount = $perInstallment;
            if ($i === $installmentCount) {
                $amount += $remainder; // bake rounding remainder into the last row
            }
            $rows[] = [
                'sequence_no' => $sequence++,
                'due_date' => $bookingDate->addMonths($i)->toDateString(),
                'amount_minor' => $amount,
                'currency' => $booking->currency,
                'category' => 'installment',
                'label' => "Installment {$i} of {$installmentCount}",
                'status' => 'due',
            ];
        }

        // Persist
        DB::transaction(function () use ($booking, $rows) {
            foreach ($rows as $row) {
                $booking->schedules()->create($row);
            }
        });

        return $rows;
    }
}
