<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UsersController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $query = User::query()->whereNull('client_id'); // staff only

        if ($request->filled('q')) {
            $q = trim((string) $request->string('q'));
            $query->where(fn ($w) =>
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%")
                  ->orWhere('phone', 'like', "%{$q}%")
            );
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($r) => $r->where('name', $request->string('role')));
        }

        $users = $query->with('roles:id,name')
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString()
            ->through(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'status' => $u->status,
                'roles' => $u->roles->pluck('name')->all(),
                'last_login_at' => $u->last_login_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['q', 'status', 'role']),
            'lookups' => [
                'roles' => $this->roleOptions(),
                'statuses' => [
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'suspended', 'label' => 'Suspended'],
                    ['value' => 'locked', 'label' => 'Locked'],
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', User::class);
        return Inertia::render('Admin/Users/Form', [
            'user' => null,
            'lookups' => ['roles' => $this->roleOptions()],
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'status' => $data['status'],
            'email_verified_at' => now(),
        ]);
        $user->syncRoles($data['roles']);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User {$user->name} created with roles: " . implode(', ', $data['roles']));
    }

    public function edit(User $user): Response
    {
        Gate::authorize('update', $user);
        return Inertia::render('Admin/Users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'roles' => $user->getRoleNames(),
            ],
            'lookups' => ['roles' => $this->roleOptions()],
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $update = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
        ];
        if (!empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }
        $user->update($update);
        $user->syncRoles($data['roles']);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User {$user->name} updated.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);
        // Soft-delete via status flip — never hard-delete a user with audit-log activity
        $user->update(['status' => 'suspended']);
        return back()->with('success', "User {$user->name} suspended.");
    }

    private function roleOptions(): array
    {
        return collect(RoleName::cases())
            ->filter(fn ($r) => $r->value !== 'client') // clients aren't created via admin UI
            ->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()])
            ->values()
            ->all();
    }
}
