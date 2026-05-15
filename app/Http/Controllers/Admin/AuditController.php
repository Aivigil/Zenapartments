<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AuditController extends Controller
{
    /**
     * Unified audit viewer:
     *  - audit_events: high-impact financial events (immutable, custom)
     *  - activity_log (Spatie): all entity field changes
     *
     * Default tab: audit_events (the more important stream).
     */
    public function index(Request $request): Response
    {
        $request->user()->can('audit.view') || abort(403);

        $stream = $request->input('stream', 'events'); // 'events' or 'activity'

        if ($stream === 'activity') {
            $logs = $this->activityLog($request);
        } else {
            $logs = $this->auditEvents($request);
        }

        return Inertia::render('Admin/Audit/Index', [
            'stream' => $stream,
            'logs' => $logs,
            'filters' => $request->only(['q', 'event', 'actor', 'days']),
            'lookups' => [
                'event_kinds' => AuditEvent::query()->distinct()->orderBy('event')->pluck('event')->all(),
                'actors' => User::whereNull('client_id')->orderBy('name')->get(['id', 'name', 'email'])
                    ->map(fn ($u) => ['id' => $u->id, 'label' => "{$u->name} ({$u->email})"]),
            ],
        ]);
    }

    private function auditEvents(Request $request): array
    {
        $query = AuditEvent::query()->with('actor:id,name,email');

        if ($request->filled('q')) {
            $q = trim((string) $request->string('q'));
            $query->where(fn ($w) => $w
                ->where('event', 'like', "%{$q}%")
                ->orWhere('reason', 'like', "%{$q}%")
                ->orWhere('subject_type', 'like', "%{$q}%")
            );
        }
        if ($request->filled('event')) {
            $query->where('event', $request->string('event'));
        }
        if ($request->filled('actor')) {
            $query->where('actor_id', $request->integer('actor'));
        }
        if ($request->filled('days')) {
            $query->where('occurred_at', '>=', now()->subDays($request->integer('days')));
        }

        return $query->orderByDesc('occurred_at')
            ->paginate(50)
            ->withQueryString()
            ->through(fn ($e) => [
                'id' => $e->id,
                'occurred_at' => $e->occurred_at?->format('Y-m-d H:i:s'),
                'event' => $e->event,
                'actor' => $e->actor ? [
                    'id' => $e->actor->id,
                    'label' => $e->actor->name . ' (' . $e->actor->email . ')',
                ] : null,
                'actor_role' => $e->actor_role,
                'subject_type' => class_basename($e->subject_type),
                'subject_id' => $e->subject_id,
                'reason' => $e->reason,
                'ip' => $e->ip,
                'before' => $e->before,
                'after' => $e->after,
            ])->toArray();
    }

    private function activityLog(Request $request): array
    {
        $query = Activity::query()->with('causer:id,name,email');

        if ($request->filled('q')) {
            $q = trim((string) $request->string('q'));
            $query->where(fn ($w) => $w
                ->where('description', 'like', "%{$q}%")
                ->orWhere('subject_type', 'like', "%{$q}%")
                ->orWhere('event', 'like', "%{$q}%")
            );
        }
        if ($request->filled('days')) {
            $query->where('created_at', '>=', now()->subDays($request->integer('days')));
        }

        return $query->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString()
            ->through(fn ($a) => [
                'id' => $a->id,
                'occurred_at' => $a->created_at?->format('Y-m-d H:i:s'),
                'event' => $a->event ?: $a->description,
                'actor' => $a->causer ? [
                    'id' => $a->causer->id,
                    'label' => ($a->causer->name ?? 'system') . ' (' . ($a->causer->email ?? '—') . ')',
                ] : null,
                'subject_type' => $a->subject_type ? class_basename($a->subject_type) : '—',
                'subject_id' => $a->subject_id,
                'description' => $a->description,
                'properties' => $a->properties?->toArray(),
            ])->toArray();
    }
}
