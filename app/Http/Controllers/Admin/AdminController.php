<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    public function index(Request $request): Response
    {
        $request->user()->can('users.view') || abort(403);

        return Inertia::render('Admin/Index', [
            'stats' => [
                'users_active' => User::whereNull('client_id')->where('status', 'active')->count(),
                'users_suspended' => User::whereNull('client_id')->where('status', 'suspended')->count(),
                'roles' => Role::count(),
                'permissions' => Permission::count(),
                'audit_events_today' => AuditEvent::whereDate('occurred_at', today())->count(),
                'audit_events_30d' => AuditEvent::where('occurred_at', '>=', now()->subDays(30))->count(),
            ],
        ]);
    }
}
