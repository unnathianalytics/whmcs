<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * What: Seeds the global permission catalog and, per company, the four default tenant roles.
 * Why: spatie permissions are global (not team-scoped), but role↔permission and role↔user
 *      assignments ARE team-scoped via `team_id = company_id`. This seeder creates the catalog once
 *      and the default roles (`manager`, `billing`, `support`, `read-only`) for each company so a
 *      new tenant starts with a working RBAC baseline. Gates check permissions, never role names.
 * When: Run from DatabaseSeeder after companies exist, and re-usable when onboarding new tenants.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * The full permission catalog (module.action). Gates check these strings directly.
     *
     * @var list<string>
     */
    public const PERMISSIONS = [
        'dashboard.view',
        'clients.view', 'clients.create', 'clients.update', 'clients.delete',
        'services.view', 'services.create', 'services.update', 'services.delete',
        'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete',
        'tickets.view', 'tickets.create', 'tickets.update', 'tickets.delete',
        'domains.view', 'domains.create', 'domains.update', 'domains.delete',
        'reminders.view', 'reminders.manage',
        'roles.view', 'roles.manage',
        'settings.view', 'settings.manage',
    ];

    public function run(): void
    {
        $this->syncPermissions();

        Company::all()->each(fn (Company $company) => self::seedRolesForCompany($company));
    }

    /**
     * What: Ensure every permission in the catalog exists for the `web` guard.
     * Why: Permissions are global; creating them once is enough for all tenants.
     * When: Called at the start of the seeder and whenever the catalog changes.
     */
    protected function syncPermissions(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    /**
     * What: Create the four default roles for a single company and grant their permissions.
     * Why: Each tenant needs its own role rows (same names, different `team_id`) so role assignments
     *      never leak across companies. `manager` gets everything; the rest get scoped subsets.
     * When: Called when seeding demo data and when a new company is onboarded.
     */
    public static function seedRolesForCompany(Company $company): void
    {
        // Scope subsequent role creation to this company's team id.
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        $manager = Role::findOrCreate('manager', 'web');
        $manager->syncPermissions(self::PERMISSIONS);

        $billing = Role::findOrCreate('billing', 'web');
        $billing->syncPermissions([
            'dashboard.view',
            'clients.view',
            'invoices.view', 'invoices.create', 'invoices.update',
            'services.view',
        ]);

        $support = Role::findOrCreate('support', 'web');
        $support->syncPermissions([
            'dashboard.view',
            'clients.view',
            'tickets.view', 'tickets.create', 'tickets.update',
        ]);

        $readOnly = Role::findOrCreate('read-only', 'web');
        $readOnly->syncPermissions(array_values(array_filter(
            self::PERMISSIONS,
            fn (string $permission): bool => str_ends_with($permission, '.view'),
        )));
    }
}
