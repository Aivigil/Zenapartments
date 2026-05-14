<?php

namespace App\Services\Notifications;

use App\Models\Client;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Services\Notifications\Channels\Channel;
use App\Services\Notifications\Channels\EmailChannel;
use App\Services\Notifications\Channels\SmsChannel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Central entry point for sending notifications.
 *
 * Usage:
 *   app(NotificationService::class)->send(
 *       'payment_reminder_upcoming',
 *       $client,
 *       ['channel' => 'email', 'data' => ['amount' => 50000, 'due_date' => '2026-08-01', ...]]
 *   );
 *
 * Pipeline:
 *   1. Resolve template by code.
 *   2. Render subject + body with mustache-style {{var}} substitution.
 *   3. Pick the recipient address based on channel (email/phone/etc.).
 *   4. Check quiet hours + per-client mute toggles.
 *   5. Create NotificationLog row (queued) and dispatch via channel adapter.
 */
class NotificationService
{
    /** @var array<string, Channel> */
    private array $channels = [];

    public function __construct(EmailChannel $email, SmsChannel $sms)
    {
        $this->channels = [
            'email' => $email,
            'sms' => $sms,
        ];
    }

    public function send(
        string $templateCode,
        Client $client,
        array $opts = []
    ): ?NotificationLog {
        $channel = $opts['channel'] ?? 'email';
        $data = $opts['data'] ?? [];
        $bookingId = $opts['booking_id'] ?? null;
        $scheduleId = $opts['schedule_id'] ?? null;

        $template = NotificationTemplate::where('code', $templateCode)
            ->where('channel', $channel)
            ->where('active', true)
            ->first();

        if (! $template) {
            Log::warning("Notification template '{$templateCode}' for channel '{$channel}' not found.");
            return null;
        }

        // Mute toggles per channel
        $prefs = $client->preferences ?? [];
        if (! empty($prefs['mute'][$channel])) {
            return null;
        }

        // Build merge data — add common client fields
        $mergeData = array_merge([
            'client_name' => $client->full_name,
            'client_code' => $client->code,
            'company' => config('app.name'),
        ], $data);

        $subject = $this->render($template->subject, $mergeData);
        $body = $this->render($template->body, $mergeData);

        $recipient = $this->recipientFor($client, $channel);
        if (! $recipient) {
            Log::info("Skipping notification — no {$channel} recipient for client {$client->code}.");
            return null;
        }

        // Quiet hours (don't suppress email; only SMS/WhatsApp)
        $status = 'queued';
        if (in_array($channel, ['sms', 'whatsapp']) && $this->isQuietHours()) {
            $status = 'suppressed';
        }

        $log = NotificationLog::create([
            'client_id' => $client->id,
            'booking_id' => $bookingId,
            'schedule_id' => $scheduleId,
            'notification_template_id' => $template->id,
            'template_code' => $template->code,
            'channel' => $channel,
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
            'status' => $status,
            'queued_at' => now(),
        ]);

        if ($status === 'queued' && isset($this->channels[$channel])) {
            $this->channels[$channel]->send($log);
        }

        return $log->fresh();
    }

    private function render(?string $template, array $data): string
    {
        if (! $template) return '';
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            fn ($m) => (string) ($data[$m[1]] ?? "{{$m[1]}}"),
            $template
        );
    }

    private function recipientFor(Client $client, string $channel): ?string
    {
        return match ($channel) {
            'email' => $client->email,
            'sms', 'whatsapp' => $client->primary_phone,
            default => null,
        };
    }

    private function isQuietHours(): bool
    {
        $tz = config('app.timezone', 'Asia/Karachi');
        $hour = (int) now($tz)->format('H');
        $start = (int) config('app.reminders.quiet_start', 21);
        $end = (int) config('app.reminders.quiet_end', 9);

        // Wraps midnight: quiet from 21:00 → 09:00
        return $start > $end
            ? ($hour >= $start || $hour < $end)
            : ($hour >= $start && $hour < $end);
    }
}
