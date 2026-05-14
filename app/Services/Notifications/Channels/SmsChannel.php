<?php

namespace App\Services\Notifications\Channels;

use App\Models\NotificationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS dispatch. In staging, with SMS_PROVIDER unset, this logs to the
 * standard log channel and marks the NotificationLog row as 'sent' so
 * the workflow can be tested end-to-end before a real gateway is wired up.
 *
 * When SMS_PROVIDER is configured (e.g. 'http_post'), it'll fire a real
 * POST to SMS_API_URL with the message body. Provider adapters can be
 * added by extending this class or creating new Channel classes.
 */
class SmsChannel implements Channel
{
    public function send(NotificationLog $log): void
    {
        if (! $log->recipient) {
            $log->update(['status' => 'failed', 'failed_at' => now(), 'failure_reason' => 'No recipient phone']);
            return;
        }

        $provider = (string) config('app.sms_provider', env('SMS_PROVIDER'));

        // No provider configured — log + mark sent (dev/staging mode)
        if (! $provider || $provider === 'null') {
            Log::info('[SMS dev-stub] would dispatch', [
                'to' => $log->recipient,
                'body' => $log->body,
            ]);
            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
                'provider' => 'dev-stub',
                'provider_response' => ['note' => 'SMS_PROVIDER not configured; logged only'],
            ]);
            return;
        }

        try {
            $response = Http::asForm()
                ->withHeaders(['Authorization' => 'Bearer ' . env('SMS_API_KEY')])
                ->post(env('SMS_API_URL'), [
                    'to' => $log->recipient,
                    'from' => env('SMS_FROM', 'ZENRETREAT'),
                    'body' => $log->body,
                ]);

            $log->update([
                'status' => $response->successful() ? 'sent' : 'failed',
                'sent_at' => $response->successful() ? now() : null,
                'failed_at' => $response->failed() ? now() : null,
                'provider' => $provider,
                'provider_response' => $response->json() ?: ['status' => $response->status()],
                'failure_reason' => $response->failed() ? 'HTTP ' . $response->status() : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('SMS dispatch failed', ['log_id' => $log->id, 'error' => $e->getMessage()]);
            $log->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => substr($e->getMessage(), 0, 500),
            ]);
        }
    }

    public function name(): string
    {
        return 'sms';
    }
}
