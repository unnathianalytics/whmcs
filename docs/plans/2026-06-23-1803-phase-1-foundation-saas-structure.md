# Phase 1 — Foundation & SaaS Structure

**Date:** 2026-06-23 18:03
**Status:** In progress

## Goal

Establish the multi-tenant SaaS foundation for the WHMCS-like panel:

- SaaS Admin (`is_saas_admin`) vs. Company Admin (`company_id`) two-tier model.
- `Company`, `SaasPlan`, `CompanySubscription` data layer.
- Company-scoped RBAC via **spatie/laravel-permission with teams enabled** (`team_id = company_id`).
- spatie/laravel-activitylog wired on key models.
- `/saas/*` and `/admin/*` route groups guarded by dedicated middleware.
- Company-admin sidebar with all module links (placeholders OK).
- SaaS Admin company list + create-company screen.
- Dashboard shell (stat cards + empty chart placeholders).

End state: a logged-in SaaS admin lands on `/saas`, can list/create companies; a company admin lands on `/admin` and sees the dashboard shell + module sidebar. Stop for manual verification after this phase.

## Discovery Notes (current repo state)

- Fresh Livewire 4 + Flux **Pro** starter kit. Auth via Fortify. `fortify.home = /dashboard`.
- DB driver: **sqlite** (dev). No production data — safe to `migrate:fresh`.
- spatie **permission** + **activitylog** tables already migrated, but `config/permission.teams = false` → **no `team_id` column** on `roles` / `model_has_roles` / `model_has_permissions`. Must enable teams and re-create those tables.
- `User` model uses PHP 8 attributes `#[Fillable]` / `#[Hidden]`, `casts()` with `password => hashed`. No roles trait yet.
- Sidebar at `resources/views/layouts/app/sidebar.blade.php` has only a Dashboard link.
- `routes/web.php` has a single `/dashboard` view route behind `auth,verified`.
- `AppServiceProvider` already sets CarbonImmutable + prod password rules.

## Decisions (confirmed with user)

1. **RBAC:** Enable spatie teams; `team_id` resolves to the authenticated user's `company_id`. Roles are per-company.
2. **Scope:** Build **Phase 1 only**, then halt for manual verification.

## Affected Items

### New migrations
- `companies` — id, name, slug (unique), email, phone, address, plan_id (nullable FK saas_plans), trial_ends_at, suspended_at, timestamps, softDeletes.
- `saas_plans` — id, name, slug, price (decimal), interval (enum monthly/annual), limits (json), is_active, timestamps.
- `company_subscriptions` — id, company_id FK, saas_plan_id FK, status (enum trialing/active/past_due/cancelled), starts_at, ends_at, timestamps.
- `add_saas_columns_to_users` — `is_saas_admin` bool default false, `company_id` nullable FK companies (nullOnDelete).
- `add_team_id_to_permission_tables` — set `config('permission.teams')=true` first; add `team_id` to `roles`, `model_has_roles`, `model_has_permissions` matching spatie's teams layout (drop/recreate uniques as spatie expects). Because dev DB is disposable, prefer **editing the existing permission migration** is risky (already run); instead do `migrate:fresh` after flipping config so the original migration emits team columns. Add a thin follow-up migration only if needed. **Approach chosen:** flip config + `migrate:fresh --seed`.

### Config
- `config/permission.php`: `teams => true`, `models.team` left null (we resolve team id manually), keep default `team_foreign_key = team_id`.

### Models (+ factories + PHPDoc What/Why/When)
- `Company` (HasFactory, SoftDeletes, LogsActivity) — relations: subscription, plan, users, (later clients...). Helpers: `isSuspended()`, `onTrial()`.
- `SaasPlan` (HasFactory, LogsActivity) — `limits` cast array; relation subscriptions.
- `CompanySubscription` (HasFactory, LogsActivity) — belongsTo company, plan; `isActive()`.
- `User` — add `HasRoles` trait, add `is_saas_admin`/`company_id` to fillable + casts (`is_saas_admin => bool`), add `company()` relation, `isSaasAdmin()` helper.

### Middleware
- `EnsureSaasAdmin` (alias `saas_admin`) — abort 403 unless `auth()->user()->is_saas_admin`.
- `EnsureCompanyAdmin` (alias `company_admin`) — require non-null `company_id`, not a saas admin, company not suspended, subscription active (or trialing). Set spatie team id via `setPermissionsTeamId(company_id)`.
- Register aliases in `bootstrap/app.php`.

### Team resolution
- In a service provider (`AppServiceProvider::boot` or new `PermissionServiceProvider`): on authenticated requests, call `app(PermissionRegistrar::class)->setPermissionsTeamId(auth()->user()->company_id)`. Done inside `EnsureCompanyAdmin` to keep it request-scoped.

### Routes
- `routes/web.php`: replace generic dashboard. Add:
  - `Route::middleware(['auth','verified'])` → redirect `/dashboard` to `/saas` or `/admin` based on user type (small redirect controller/closure).
  - `/saas` group (`auth,verified,saas_admin`): `saas.dashboard`, `saas.companies` (Livewire index), create handled in modal.
  - `/admin` group (`auth,verified,company_admin`): `admin.dashboard` + placeholder routes for clients/services/invoices/tickets/domains/reminders/roles/settings (can point to a single "coming soon" component for now or be named-only). Keep minimal: dashboard real, others as sidebar links to `#` placeholders to avoid dead routes. **Chosen:** only wire real routes for dashboard + (saas) companies; module links render but point to `admin.dashboard` with a note, OR use `route()` only where defined. To keep sidebar clean, gate module links as non-links (disabled) for now.

### Livewire components (class-based)
- `App\Livewire\Saas\Dashboard` — MRR/active tenants/churn stat cards (computed from data).
- `App\Livewire\Saas\Companies\Index` — Flux table list + create/edit/suspend via modal, search + pagination.
- `App\Livewire\Admin\Dashboard` — stat cards (clients/services/tickets/revenue — zeros for now) + chart placeholder + expiring/expired empty lists.

### Views / layout
- New company-admin sidebar partial OR extend existing sidebar with conditional groups based on `is_saas_admin`. **Chosen:** branch inside `sidebar.blade.php` — SaaS group vs Company group.
- Blade views for the Livewire components under `resources/views/livewire/...`.

### Seeders (+ factories)
- `SaasPlanSeeder` — Starter/Pro/Agency with limits json.
- `RolesAndPermissionsSeeder` — seed permission set (clients.*, services.*, invoices.*, tickets.*, domains.*, reminders.*, roles.*, settings.*, dashboard.view) for guard `web`. Roles are team-scoped, so seed per-company in CompanySeeder.
- `DatabaseSeeder` —
  - SaaS admin: `admin@admin.com` / `admin@admin.com`, `is_saas_admin=true`.
  - Permissions (global, team-agnostic in spatie — permissions are not team scoped; only role assignments are).
  - 1 demo `Company` + subscription on Pro, with default roles (`manager`,`billing`,`support`,`read-only`) created with that company's `team_id`, permissions synced, and 1 company-admin user assigned `manager`.

## Implementation Steps

1. Flip `config/permission.php` teams → true.
2. Create SaaS migrations (companies, saas_plans, company_subscriptions) + users alter.
3. Create models + factories with PHPDoc.
4. Update `User` (HasRoles, fillable, casts, relations).
5. Create middleware + register aliases.
6. Routes: redirect + `/saas` + `/admin` groups.
7. Livewire components + Blade views (Flux UI).
8. Sidebar branching.
9. Seeders (plans, permissions, demo company, admin).
10. `php artisan migrate:fresh --seed`.
11. Pint format. Write/Run tests.

## Verification / Testing Plan

- **Feature tests (Pest):**
  - SaaS admin can reach `/saas`, company admin cannot (403) and vice-versa.
  - Unauthenticated → redirected to login.
  - `/dashboard` redirects each user type to the right home.
  - Company creation via Livewire persists a company + seeds its roles.
  - Suspended company blocks company-admin access.
  - Permission team id is set to the user's company on admin requests.
- **Manual:** log in as `admin@admin.com` → see SaaS dashboard + company list + create company. Log in as seeded company admin → see admin dashboard + module sidebar.
- `php artisan test --compact` (filtered) + `vendor/bin/pint`.

## Risks & Assumptions

- **migrate:fresh wipes dev DB** — acceptable (no prod data, sqlite). Confirmed safe.
- Enabling teams changes permission table PKs; existing seeded data is disposable.
- Permissions in spatie are **global**, not team-scoped — only **role↔user assignments** carry `team_id`. Seeder must create the same role *name* once per company (spatie allows duplicate role names across teams).
- Module routes are placeholders; later phases replace them. Avoid `route()` calls to undefined names in the sidebar.
- Flux Pro components assumed available (confirmed installed).

## Postponed (later phases)

- Clients/services/invoices/tickets/domains/reminders modules (Phases 2–7).
- Real dashboard charts + expiry widgets (Phase 8).
- Impersonation, SaaS plan CRUD UI, churn analytics (Phase 9).
- Role–Permission management UI (Phase 8).
