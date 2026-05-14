<?php

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditEventWriter
{
    /**
     * Append an immutable audit event.
     *
     * @param  string  $event    Dotted event name, e.g. "payment.posted"
     * @param  Model   $subject  The entity the event happened to
     * @param  array   $before   Snapshot of relevant fields before the change
     * @param  array   $after    Snapshot of relevant fields after the change
     * @param  string|null $reason  Operator-supplied reason where relevant
     */
    public static function record(
        string $event,
        Model $subject,
        array $before = [],
        array $after = [],
        ?string $reason = null,
    ): AuditEvent {
        $ctx = Request::attributes->get('audit_context', []);

        return AuditEvent::create([
            'actor_id'    => $ctx['actor_id'] ?? null,
            'actor_role'  => $ctx['actor_role'] ?? null,
            'event'       => $event,
            'subject_type' => $subject::class,
            'subject_id'  => $subject->getKey(),
            'before'      => $before ?: null,
            'after'       => $after ?: null,
            'reason'      => $reason,
            'ip'          => $ctx['ip'] ?? null,
            'user_agent'  => $ctx['user_agent'] ?? null,
            'occurred_at' => $ctx['occurred_at'] ?? now(),
        ]);
    }
}
