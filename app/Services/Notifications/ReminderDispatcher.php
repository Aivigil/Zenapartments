<?php

namespace App\Services\Notifications;

use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Finds Schedule rows that need a reminder right now and dispatches them.
 *
 * Strategy:
 *   - Upcoming reminders (T-15, -7, -3, -1 days before due_date)
 *   - Overdue escalation (D+1, +7, +15, +30 after due_date)
 *
 * Each schedule × trigger combination only fires once per template (we check
 * notification_log for an existing entry with the same template_code + schedule_id).
 */
class ReminderDispatcher
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function run(?CarbonImmutable $now = null): array
    {
        $now = ($now ?: CarbonImmutable::now())->startOfDay();
        $stats = ['upcoming' => 0, 'overdue' => 0, 'skipped' => 0];

        $upcomingDays = config('app.reminders.upcoming_days', [15, 7, 3, 1]);
        $overdueDays = config('app.reminders.overdue_days', [1, 7, 15, 30]);

        // Upcoming
        foreach ($upcomingDays as $days) {
            $target = $now->addDays($days)->toDateString();
            $stats['upcoming'] += $this->fireForDate($target, "reminder_upcoming_t{$days}", 'upcoming', $days);
        }

        // Overdue
        foreach ($overdueDays as $days) {
            $target = $now->subDays($days)->toDateString();
            $stats['overdue'] += $this->fireForDate($target, "reminder_overdue_d{$days}", 'overdue', $days);
        }

        Log::info('ReminderDispatcher run complete', $stats);
        return $stats;
    }

    private function fireForDate(string $dueDate, string $templateCode, string $kind, int $days): int
    {
        // Find schedule rows due exactly on the target date that aren't paid/cancelled/waived
        $rows = Schedule::with(['booking.client'])
            ->whereDate('due_date', $dueDate)
            ->whereIn('status', ['due', 'partially_paid'])
            ->get();

        $count = 0;

        foreach ($rows as $schedule) {
            $client = $schedule->booking?->client;
            if (! $client) continue;

            // Idempotency: skip if we've already fired this template against this schedule
            $alreadyFired = \DB::table('notification_log')
                ->where('schedule_id', $schedule->id)
                ->where('template_code', $templateCode)
                ->whereIn('status', ['queued', 'sent', 'delivered'])
                ->exists();
            if ($alreadyFired) continue;

            $owedMinor = $schedule->amount_minor - $schedule->paid_minor;
            $data = [
                'amount' => money_format_pkr($owedMinor, false),
                'amount_with_symbol' => money_format_pkr($owedMinor),
                'due_date' => CarbonImmutable::parse($schedule->due_date)->format('d M Y'),
                'days' => $days,
                'booking_code' => $schedule->booking->code,
                'item' => $schedule->label,
            ];

            // Try email first; fall back to SMS if no email on file.
            $sent = false;
            if ($client->email) {
                $log = $this->notifications->send($templateCode, $client, [
                    'channel' => 'email',
                    'booking_id' => $schedule->booking_id,
                    'schedule_id' => $schedule->id,
                    'data' => $data,
                ]);
                $sent = $log !== null;
            }

            if (! $sent && $client->primary_phone) {
                $this->notifications->send($templateCode, $client, [
                    'channel' => 'sms',
                    'booking_id' => $schedule->booking_id,
                    'schedule_id' => $schedule->id,
                    'data' => $data,
                ]);
                $sent = true;
            }

            if ($sent) $count++;
        }

        return $count;
    }
}
