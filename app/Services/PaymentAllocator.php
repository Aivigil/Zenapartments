<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Allocates a Payment against open Schedule rows of its Booking.
 *
 * Default strategy: FIFO against the oldest due_date that's not fully paid.
 * Caller can pass an explicit `[schedule_id => amount_minor]` map to override.
 */
class PaymentAllocator
{
    /**
     * @param  Payment  $payment      The posted payment to allocate
     * @param  array<int, int>|null $explicit  Optional explicit map of schedule_id => amount_minor
     */
    public function allocate(Payment $payment, ?array $explicit = null): array
    {
        if ($payment->status->value !== 'posted') {
            throw new \LogicException("Cannot allocate a non-posted payment (status: {$payment->status->value}).");
        }

        return DB::transaction(function () use ($payment, $explicit) {
            $booking = $payment->booking;
            if (! $booking) {
                throw new \LogicException("Payment {$payment->code} has no booking; cannot allocate.");
            }

            $payable = (int) ($payment->pkr_amount_minor ?? $payment->amount_minor);
            $alreadyAllocated = (int) $payment->allocations()->sum('amount_minor');
            $remaining = $payable - $alreadyAllocated;
            if ($remaining <= 0) {
                return [];
            }

            $created = [];

            if ($explicit) {
                foreach ($explicit as $scheduleId => $amount) {
                    if ($remaining <= 0) break;
                    $schedule = $booking->schedules()->whereKey($scheduleId)->lockForUpdate()->first();
                    if (! $schedule) continue;

                    $take = (int) min($amount, $remaining, $schedule->amount_minor - $schedule->paid_minor);
                    if ($take <= 0) continue;

                    $created[] = Allocation::create([
                        'payment_id' => $payment->id,
                        'schedule_id' => $schedule->id,
                        'amount_minor' => $take,
                        'currency' => $payment->currency,
                    ]);
                    $schedule->paid_minor += $take;
                    $schedule->status = $this->scheduleStatusFor($schedule);
                    if ($schedule->status === 'paid' && ! $schedule->paid_on) {
                        $schedule->paid_on = $payment->received_at;
                    }
                    $schedule->save();
                    $remaining -= $take;
                }
            } else {
                // FIFO: oldest open schedule rows first
                $rows = $booking->schedules()
                    ->whereIn('status', ['due', 'partially_paid'])
                    ->orderBy('due_date')
                    ->orderBy('sequence_no')
                    ->lockForUpdate()
                    ->get();

                foreach ($rows as $schedule) {
                    if ($remaining <= 0) break;
                    $owed = $schedule->amount_minor - $schedule->paid_minor;
                    if ($owed <= 0) continue;

                    $take = (int) min($owed, $remaining);
                    $created[] = Allocation::create([
                        'payment_id' => $payment->id,
                        'schedule_id' => $schedule->id,
                        'amount_minor' => $take,
                        'currency' => $payment->currency,
                    ]);
                    $schedule->paid_minor += $take;
                    $schedule->status = $this->scheduleStatusFor($schedule);
                    if ($schedule->status === 'paid' && ! $schedule->paid_on) {
                        $schedule->paid_on = $payment->received_at;
                    }
                    $schedule->save();
                    $remaining -= $take;
                }
            }

            return $created;
        });
    }

    /** Reverses a payment's allocations (used when reversing a payment). */
    public function unallocate(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            foreach ($payment->allocations()->lockForUpdate()->get() as $alloc) {
                $schedule = $alloc->schedule()->lockForUpdate()->first();
                if ($schedule) {
                    $schedule->paid_minor = max(0, $schedule->paid_minor - $alloc->amount_minor);
                    $schedule->status = $this->scheduleStatusFor($schedule);
                    if ($schedule->status !== 'paid') {
                        $schedule->paid_on = null;
                    }
                    $schedule->save();
                }
                $alloc->delete();
            }
        });
    }

    private function scheduleStatusFor($schedule): string
    {
        if ($schedule->paid_minor >= $schedule->amount_minor) return 'paid';
        if ($schedule->paid_minor > 0) return 'partially_paid';
        return 'due';
    }
}
