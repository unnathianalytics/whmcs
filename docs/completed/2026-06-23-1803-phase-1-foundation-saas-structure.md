# Phase 1 — Foundation & SaaS Structure (Completion Report)

**Date:** 2026-06-23
**Plan:** `docs/plans/2026-06-23-1803-phase-1-foundation-saas-structure.md`
**Status:** ✅ Completed & user-verified

## Summary

Built the multi-tenant SaaS foundation for the WHMCS-like panel:

- Two-tier user model on the existing `User` (`is_saas_admin` for the platform owner, `company_id` for tenant admins).
- `Company`, `SaasPlan`, `CompanySubscription` data layer with factories and activity logging.
- Company-scoped RBAC via **spatie/laravel-permission with teams enabled** (`team_id = company_id`): 27 permissions + 4 default roles (`manager`, `billing`, `support`, `read-only`) seeded per company.
- `EnsureSaasAdmin` / `EnsureCompanyAdmin` middleware guarding `/saas/*` and `/admin/*`; the company-admin middleware also binds the spatie permission team id to the request.
- Post-login `/dashboard` redirect that routes each tier to its home.
- Flux UI screens: SaaS dashboard (MRR / active tenants / churn), company list with create + suspend/reactivate, company-admin dashboard shell with stat cards and expiry-widget placeholders.
- Branched sidebar (SaaS nav vs. company nav with disabled module placeholders).

## Changelog

### Added
- `database/migrations/2026_06_23_123433_create_saas_plans_table.php`
- `database/migrations/2026_06_23_123434_create_companies_table.php`
- `database/migrations/2026_06_23_123435_create_company_subscriptions_table.php`
- `database/migrations/2026_06_23_123436_add_saas_columns_to_users_table.php`
- `app/Models/Company.php`, `app/Models/SaasPlan.php`, `app/Models/CompanySubscription.php`
- `database/factories/CompanyFactory.php`, `SaasPlanFactory.php`, `CompanySubscriptionFactory.php`
- `app/Http/Middleware/EnsureSaasAdmin.php`, `app/Http/Middleware/EnsureCompanyAdmin.php`
- `app/Http/Controllers/DashboardRedirectController.php`
- `app/Livewire/Saas/Dashboard.php`, `app/Livewire/Saas/Companies/Index.php`, `app/Livewire/Admin/Dashboard.php`
- `resources/views/livewire/saas/dashboard.blade.php`, `resources/views/livewire/saas/companies/index.blade.php`, `resources/views/livewire/admin/dashboard.blade.php`
- `database/seeders/SaasPlanSeeder.php`, `database/seeders/RolesAndPermissionsSeeder.php`
- `tests/Feature/SaasFoundationTest.php`
- `docs/plans/2026-06-23-1803-phase-1-foundation-saas-structure.md` (plan)

### Changed
- `config/permission.php` — `teams => true`.
- `app/Models/User.php` — `HasRoles` trait, `is_saas_admin`/`company_id` fillable + cast, `company()` relation, `isSaasAdmin()` helper.
- `bootstrap/app.php` — registered `saas_admin` / `company_admin` middleware aliases.
- `routes/web.php` — `/dashboard` redirect, `/saas/*` and `/admin/*` route groups.
- `resources/views/layouts/app/sidebar.blade.php` — tier-branched navigation.
- `database/seeders/DatabaseSeeder.php` — SaaS admin, demo company + subscription, per-company roles, demo company admin.
- `tests/Pest.php` — `companyAdmin()` test helper.
- `tests/Feature/DashboardTest.php` — updated for the new two-tier redirect behaviour.

## Validation

- `php artisan migrate:fresh --seed` — succeeds; permission tables regenerated with `team_id`.
- Data verification (tinker): 1 SaaS admin, 1 demo company, 3 plans, 1 subscription, 27 permissions, 4 team-scoped roles; demo manager holds all 27 permissions under `team_id=1`.
- `php artisan test --compact` — **45 passed, 100 assertions**. New `SaasFoundationTest` covers: tier access control (saas/company/guest), no-company lockout, suspended-company lockout, team-id scoping, and company create/validate/suspend via Livewire.
- `vendor/bin/pint --dirty` — clean.
- Manual verification by user: login as both tiers, company create + suspend/reactivate — confirmed working.

## Discovery Notes / Deviations

- spatie permission tables were already migrated with `teams=false`; enabling teams required `migrate:fresh` (safe — disposable sqlite dev DB, no production data).
- **activitylog v5 API differences corrected:** trait namespace is `Spatie\Activitylog\Models\Concerns\LogsActivity`, `LogOptions` is `Spatie\Activitylog\Support\LogOptions`, and `dontSubmitEmptyLogs()` is now `dontLogEmptyChanges()`.
- Permission registrar cache must be flushed mid-seed (`forgetCachedPermissions()`) so freshly-created permissions are visible when syncing them to roles.
- Seeded SaaS admin name is `"SaaS Admin"` (idea doc used `"Admin"`) — accepted by user.

## Postponed Items

- Clients / Services / Invoices / Tickets / Domains / Reminders modules (Phases 2–7).
- Real dashboard metrics + revenue/expiry charts (Phase 8).
- Role–Permission management UI and activity-log viewer (Phase 8).
- SaaS plan CRUD, subscription assignment UI, company impersonation, churn analytics (Phase 9).

## Follow-up Recommendations

- Extract per-company role seeding (`RolesAndPermissionsSeeder::seedRolesForCompany`) into a dedicated action and call it from `Saas\Companies\Index::createCompany` so new tenants get roles immediately (currently only seeded tenants have roles).
- Add a global `BelongsToCompany` scope/trait before building tenant-scoped models (Phase 2) to enforce `company_id` isolation by default.
- Consider enforcing plan `limits` (max_clients / max_admins) when those modules land.
- Module sidebar links are disabled placeholders; wire them as each phase ships.
