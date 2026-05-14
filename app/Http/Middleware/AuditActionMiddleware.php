<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Captures actor + IP + UA on every request and stores them on the request
 * for downstream audit-event writers to use. Doesn't write anything itself.
 */
class AuditActionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $request->attributes->set('audit_context', [
            'actor_id'   => $user?->id,
            // ?->method()?->method() chains safely — if $user is null OR
            // getRoleNames() returns null, actor_role becomes null without exploding.
            'actor_role' => $user?->getRoleNames()?->first(),
            'ip'         => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'occurred_at' => now(),
        ]);

        return $next($request);
    }
}
