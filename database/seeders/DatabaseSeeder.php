<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\SaasPlan;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * What: Bootstraps the application with a SaaS admin, default plans, and a demo tenant.
 * Why: Gives a working two-tier login out of the box — `admin@admin.com` for the platform owner and
 *      a seeded company admin for the tenant side — plus the RBAC baseline every tenant needs.
 * When: Run via `php artisan db:seed` or `migrate:fresh --seed`.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            SaasPlanSeeder::class,
        ]);

        // Tier 1 — platform owner. No company; global scope.
        User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'SaaS Admin',
                'password' => 'admin@admin.com', // hashed via the User 'hashed' cast
                'is_saas_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        // Reset the cached roles & permissions so freshly-created records are visible this run.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Seed the global permission catalog before any roles are created.
        foreach (RolesAndPermissionsSeeder::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Tier 2 — a demo tenant on the Pro plan, with default roles and one company admin.
        $pro = SaasPlan::where('slug', 'pro')->first();

        $company = Company::updateOrCreate(
            ['slug' => 'demo-company'],
            [
                'name' => 'Demo Company',
                'email' => 'owner@demo-company.test',
                'plan_id' => $pro?->id,
                'trial_ends_at' => now()->addDays(14),
            ],
        );

        $company->subscription()->updateOrCreate(
            ['saas_plan_id' => $pro?->id],
            [
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
            ],
        );

        RolesAndPermissionsSeeder::seedRolesForCompany($company);

        $companyAdmin = User::updateOrCreate(
            ['email' => 'manager@demo-company.test'],
            [
                'name' => 'Demo Manager',
                'password' => 'password',
                'is_saas_admin' => false,
                'company_id' => $company->id,
                'email_verified_at' => now(),
            ],
        );

        // Assign the tenant-scoped `manager` role within this company's team context.
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $companyAdmin->syncRoles(['manager']);

        // Demo client data for the Clients module (Phase 2), the product catalog & services (Phase 3),
        // then invoices, tax rates and payments (Phase 4), support tickets (Phase 5), and domains (Phase 6).
        $this->call([
            ClientSeeder::class,
            ProductSeeder::class,
            InvoiceSeeder::class,
            TicketSeeder::class,
            DomainSeeder::class,
            ReminderRuleSeeder::class,
        ]);
    }
}
