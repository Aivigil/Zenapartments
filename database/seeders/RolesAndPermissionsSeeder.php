<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permission catalog. Format: <module>.<action>
     *
     * Read actions don't need to be listed for every role — most staff get the
     * read of their own work area. Write/destructive actions are tightly scoped.
     */
    private array $permissions = [
        // Inventory
        'projects.view', 'projects.manage',
        'blocks.view', 'blocks.manage',
        'unit_categories.view', 'unit_categories.manage',
        'units.view', 'units.manage',

        // Plans
        'plan_templates.view', 'plan_templates.manage',

        // Clients & bookings
        'clients.view', 'clients.manage', 'clients.kyc',
        'bookings.view', 'bookings.manage', 'bookings.cancel',

        // Money
        'payments.view', 'payments.post', 'payments.reverse',
        'allocations.manage',
        'reconciliation.view', 'reconciliation.match', 'reconciliation.confirm',
        'adjustments.request', 'adjustments.approve',

        // Statements & reminders
        'statements.view', 'statements.generate',
        'notifications.view', 'notifications.send', 'notifications.broadcast',

        // CRM
        'crm.view', 'crm.manage',

        // Reporting
        'reports.view', 'reports.export',

        // System
        'users.view', 'users.manage',
        'roles.view', 'roles.manage',
        'settings.view', 'settings.manage',
        'audit.view',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $roles = [
            RoleName::SuperAdmin->value => $this->permissions, // all
            RoleName::FinanceAdmin->value => array_merge(
                $this->readAll(),
                [
                    'projects.manage', 'blocks.manage', 'unit_categories.manage', 'units.manage',
                    'plan_templates.manage',
                    'clients.manage', 'clients.kyc',
                    'bookings.manage', 'bookings.cancel',
                    'payments.post', 'payments.reverse', 'allocations.manage',
                    'reconciliation.match', 'reconciliation.confirm',
                    'adjustments.request', 'adjustments.approve',
                    'statements.generate', 'notifications.send', 'notifications.broadcast',
                    'reports.export', 'settings.manage',
                ]
            ),
            RoleName::FinanceOperator->value => array_merge(
                $this->readAll(),
                ['payments.post', 'allocations.manage', 'reconciliation.match', 'statements.generate']
            ),
            RoleName::Sales->value => [
                'projects.view', 'units.view', 'unit_categories.view',
                'plan_templates.view',
                'clients.view', 'bookings.view',
                'payments.view',
                'crm.view', 'crm.manage',
                'reports.view',
            ],
            RoleName::CustomerService->value => [
                'projects.view', 'units.view',
                'clients.view', 'bookings.view',
                'payments.view',
                'statements.view',
                'notifications.view', 'notifications.send',
            ],
            RoleName::Client->value => [
                // Client-scoped permissions are policy-enforced (must own the resource).
                'bookings.view', 'payments.view', 'statements.view', 'notifications.view',
            ],
            RoleName::Auditor->value => $this->readAll(),
        ];

        foreach ($roles as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }
    }

    private function readAll(): array
    {
        return array_values(array_filter(
            $this->permissions,
            fn ($p) => str_ends_with($p, '.view') || $p === 'audit.view'
        ));
    }
}
