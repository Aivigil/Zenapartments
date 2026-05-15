<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesController extends Controller
{
    public function index(Request $request): Response
    {
        $request->user()->can('roles.view') || abort(403);

        $allPermissions = Permission::orderBy('name')->pluck('name')->all();
        $roles = Role::with('permissions:id,name')->orderBy('name')->get();

        // Group permissions by module prefix (e.g. 'payments.*')
        $groups = collect($allPermissions)
            ->groupBy(fn ($p) => str_contains($p, '.') ? explode('.', $p)[0] : 'other')
            ->map(fn ($perms) => $perms->values()->all())
            ->all();

        $matrix = $roles->map(fn ($role) => [
            'name' => $role->name,
            'label' => $this->roleLabel($role->name),
            'user_count' => $role->users()->count(),
            'permission_count' => $role->permissions->count(),
            'permissions' => $role->permissions->pluck('name')->all(),
        ]);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $matrix,
            'permission_groups' => $groups,
            'all_permissions' => $allPermissions,
        ]);
    }

    private function roleLabel(string $name): string
    {
        try {
            return RoleName::from($name)->label();
        } catch (\ValueError) {
            return ucwords(str_replace('_', ' ', $name));
        }
    }
}
