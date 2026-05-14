<?php

namespace App\Services\Notifications\Channels;

use App\Models\NotificationLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailChannel implements Channel
{
    public function send(NotificationLog $log): void
    {
        if (! $log->recipient) {
            $log->update(['status' => 'failed', 'failed_at' => now(), 'failure_reason' => 'No recipient email']);
            return;
        }

        try {
            Mail::raw($log->body, function ($msg) use ($log) {
                $msg->to($log->recipient)
                    ->subject($log->subject ?: '[Zen Retreats] Notification');
            });
            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
                'provider' => config('mail.default'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Email dispatch failed', ['log_id' => $log->id, 'error' => $e->getMessage()]);
            $log->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => substr($e->getMessage(), 0, 500),
            ]);
        }
    }

    public function name(): string
    {
        return 'email';
    }
}
