<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\NotificationLog;
use App\Models\Schedule;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationsController extends Controller
{
    public function __construct(private NotificationService $notifier)
    {
    }

    public function index(Request $request): Response
    {
        $request->user()->can('notifications.view') || abort(403);

        $query = NotificationLog::query()
            ->with(['client:id,code,full_name', 'template:id,name']);

        if ($request->filled('q')) {
            $q = trim((string) $request->string('q'));
            $query->where(function ($w) use ($q) {
                $w->where('subject', 'like', "%{$q}%")
                  ->orWhere('body', 'like', "%{$q}%")
                  ->orWhere('recipient', 'like', "%{$q}%")
                  ->orWhereHas('client', fn ($c) => $c->where('full_name', 'like', "%{$q}%"));
            });
        }
        if ($request->filled('channel')) {
            $query->where('channel', $request->string('channel'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('template_code')) {
            $query->where('template_code', $request->string('template_code'));
        }

        $logs = $query->orderByDesc('queued_at')
            ->paginate(50)
            ->withQueryString()
            ->through(fn ($l) => [
                'id' => $l->id,
                'queued_at' => $l->queued_at?->format('Y-m-d H:i'),
                'sent_at' => $l->sent_at?->format('Y-m-d H:i'),
                'channel' => $l->channel,
                'template_code' => $l->template_code,
                'recipient' => $l->recipient,
                'subject' => $l->subject,
                'status' => $l->status,
                'provider' => $l->provider,
                'failure_reason' => $l->failure_reason,
                'client' => $l->client ? [
                    'id' => $l->client->id,
                    'code' => $l->client->code,
                    'full_name' => $l->client->full_name,
                ] : null,
            ]);

        return Inertia::render('Notifications/Index', [
            'logs' => $logs,
            'filters' => $request->only(['q', 'channel', 'status', 'template_code']),
            'lookups' => [
                'channels' => [
                    ['value' => 'email', 'label' => 'Email'],
                    ['value' => 'sms', 'label' => 'SMS'],
                    ['value' => 'whatsapp', 'label' => 'WhatsApp'],
                    ['value' => 'in_app', 'label' => 'In-app'],
                ],
                'statuses' => [
                    ['value' => 'queued', 'label' => 'Queued'],
                    ['value' => 'sent', 'label' => 'Sent'],
                    ['value' => 'delivered', 'label' => 'Delivered'],
                    ['value' => 'failed', 'label' => 'Failed'],
                    ['value' => 'suppressed', 'label' => 'Suppressed (quiet hours)'],
                ],
            ],
        ]);
    }

    public function show(NotificationLog $notificationLog): Response
    {
        $notificationLog->load(['client:id,code,full_name', 'template:id,name']);
        return Inertia::render('Notifications/Show', [
            'log' => [
                'id' => $notificationLog->id,
                'queued_at' => $notificationLog->queued_at?->format('Y-m-d H:i:s'),
                'sent_at' => $notificationLog->sent_at?->format('Y-m-d H:i:s'),
                'delivered_at' => $notificationLog->delivered_at?->format('Y-m-d H:i:s'),
                'failed_at' => $notificationLog->failed_at?->format('Y-m-d H:i:s'),
                'channel' => $notificationLog->channel,
                'template_code' => $notificationLog->template_code,
                'recipient' => $notificationLog->recipient,
                'subject' => $notificationLog->subject,
                'body' => $notificationLog->body,
                'status' => $notificationLog->status,
                'provider' => $notificationLog->provider,
                'provider_message_id' => $notificationLog->provider_message_id,
                'provider_response' => $notificationLog->provider_response,
                'failure_reason' => $notificationLog->failure_reason,
                'client' => $notificationLog->client,
                'booking_id' => $notificationLog->booking_id,
                'schedule_id' => $notificationLog->schedule_id,
            ],
        ]);
    }

    /**
     * Manually fire a specific reminder template against a specific schedule item.
     * Used by the "Send reminder now" action on the booking page.
     */
    public function sendForSchedule(Request $request): RedirectResponse
    {
        $request->user()->can('notifications.send') || abort(403);
        $validated = $request->validate([
            'schedule_id' => ['required', 'exists:schedules,id'],
            'template_code' => ['required', 'string'],
            'channel' => ['required', 'in:email,sms,whatsapp'],
        ]);

        $schedule = Schedule::with('booking.client')->findOrFail($validated['schedule_id']);
        $client = $schedule->booking?->client;
        abort_unless($client, 404, 'Booking has no client.');

        $owed = $schedule->amount_minor - $schedule->paid_minor;
        $log = $this->notifier->send($validated['template_code'], $client, [
            'channel' => $validated['channel'],
            'booking_id' => $schedule->booking_id,
            'schedule_id' => $schedule->id,
            'data' => [
                'amount' => money_format_pkr($owed, false),
                'amount_with_symbol' => money_format_pkr($owed),
                'due_date' => $schedule->due_date?->format('d M Y'),
                'booking_code' => $schedule->booking->code,
                'item' => $schedule->label,
            ],
        ]);

        return back()->with(
            $log ? 'success' : 'error',
            $log ? "Notification queued ({$log->status})." : 'No template / recipient — nothing sent.'
        );
    }
}
