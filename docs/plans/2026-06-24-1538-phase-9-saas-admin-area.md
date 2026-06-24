# Phase 9 — SaaS Admin Area — Plan

**Date:** 2026-06-24 15:38
**Phase:** 9 (final core phase per `docs/idea.md`)

## Goal

Complete the **Tier 1 (SaaS Admin)** platform-owner experience: a real metrics dashboard,
full company lifecycle management (create / edit / suspend / soft-delete / impersonate),
SaaS plan CRUD, and subscription assignment with trial/expiry control. This is the
cross-tenant counterpart to the company-admin panel finished in Phases 1–8.

## Discovery Notes (existing state)

Inspected directly (no sub-agents):

- **Models already exist & are activity-logged:** `Company` (SoftDeletes, `isSuspended()`,
  `onTrial()`, `subscription()` latestOfMany, `plan()`), `SaasPlan` (`price` decimal, `interval`,
  `limits` array, `is_active`, `subscriptions()`), `CompanySubscription` (`status`, `starts_at`,
  `ends_at`, `isActive()`, `company()`, `plan()`).
- **Routes:** `routes/web.php` has the `saas.` group with only `saas.dashboard` + `saas.companies`.
- **Livewire:** `App\Livewire\Saas\Dashboard` (stat computeds: activeTenants, churnedTenants,
  MRR, totalCompanies — already real) and `App\Livewire\Saas\Companies\Index`
  (list + search + create + `toggleSuspend`). Create already seeds a trialing subscription.
- **Middleware:** `EnsureSaasAdmin` (checks `is_saas_admin`), `EnsureCompanyAdmin` (checks company
  not suspended + subscription active, sets spatie team id). `DashboardRedirectController` routes
  each tier home.
- **Seeders:** `SaasPlanSeeder` (Starter/Pro/Agency), `RolesAndPermissionsSeeder::seedRolesForCompany()`
  (re-usable for onboarding a new tenant's RBAC).
- **Sidebar:** `layouts/app/sidebar.blade.php` SaaS branch currently lists Dashboard + Companies only.

**Gaps to close for Phase 9:**
1. SaaS dashboard is missing the "recent tenants" / richer overview (charts optional — keep light).
2. No edit-company, no soft-delete, no impersonation.
3. No SaaS plan management screen at all.
4. No dedicated subscription-assignment UI (create modal sets a fixed 14-day trial only; no way to
   change a plan, extend trial, mark active/cancelled, set ends_at).
5. No impersonation infra (session stash, banner, stop route).

## Affected Items

### New files
- `app/Livewire/Saas/Plans/Index.php` + `resources/views/livewire/saas/plans/index.blade.php` —
  SaaS plan CRUD (name, price, interval, limits: max_clients/max_admins, is_active).
- `app/Livewire/Saas/Companies/Show.php` + `resources/views/livewire/saas/companies/show.blade.php` —
  company detail: contact edit, subscription assignment (plan, status, starts_at, ends_at, trial),
  users list, suspend/delete/impersonate actions.
- `app/Http/Controllers/Saas/ImpersonationController.php` — `start` (login-as a company admin) and
  `stop` (restore original SaaS admin) actions.
- `app/Services/Saas/Impersonation.php` *(optional helper)* — encapsulates session stash key + swap;
  keeps controller thin and testable. Decision: include it for clean tests.
- `tests/Feature/Saas/SaasPlansTest.php`
- `tests/Feature/Saas/CompanyManagementTest.php` (edit, suspend, soft-delete, subscription assign)
- `tests/Feature/Saas/ImpersonationTest.php`
- `database/factories/` — confirm `CompanyFactory`, `SaasPlanFactory`, `CompanySubscriptionFactory`
  exist (they're referenced by models); add states if missing.

### Changed files
- `routes/web.php` — add `saas.plans`, `saas.companies.show`, and impersonation routes
  (`saas.impersonate` start outside/inside the saas group as appropriate; `impersonate.stop` must be
  reachable while authenticated as the impersonated user — place stop on a plain `auth` route).
- `app/Livewire/Saas/Companies/Index.php` — link rows to the new Show page; keep create + suspend.
- `app/Livewire/Saas/Dashboard.php` — add a `recentCompanies()` computed + (light) trialing count.
- `resources/views/livewire/saas/dashboard.blade.php` — render recent tenants table + quick links.
- `resources/views/layouts/app/sidebar.blade.php` — add **Plans** to the Platform group; render the
  **impersonation banner** when a session stash is present (visible inside the company-admin layout).
- `app/Models/Company.php` — add `subscriptions(): HasMany` (history) if needed; add a
  `companyAdmins()`/`admins()` helper for the impersonation user picker (users where company_id set).
- `app/Http/Middleware/EnsureCompanyAdmin.php` — **no change expected**; impersonation logs in as a
  real company user, so the existing checks apply naturally.

## Implementation Steps

1. **Factories check** — ensure `SaasPlanFactory`, `CompanySubscriptionFactory`, `CompanyFactory`
   exist with sane defaults; add `trialing`/`active`/`cancelled` states on the subscription factory.
2. **SaaS Plans CRUD** (`Saas\Plans\Index`) — Flux table + create/edit modal + delete (guard: block
   delete when a plan has subscriptions; offer deactivate instead). Slug auto-generated & unique.
3. **Company Show page** (`Saas\Companies\Show`):
   - Header with status badges + actions (Suspend/Reactivate, Impersonate, Delete).
   - Edit contact details (name, email, phone, address).
   - **Subscription panel:** assign/change plan, set status (trialing/active/past_due/cancelled),
     `starts_at`, `ends_at`, and trial_ends_at. Persists to `company_subscriptions` (+ keeps
     `companies.plan_id` in sync) — one source of truth via `company->subscription`.
   - Users table (company admins) with the impersonation target picker.
   - Soft-delete with a typed-name confirmation modal (Flux modal); restore deferred/out-of-scope.
4. **Impersonation:**
   - `ImpersonationController@start($user)`: authorize SaaS admin, store
     `session('impersonator_id' => auth()->id())`, `Auth::login($companyUser)`, redirect to
     `admin.dashboard`. Refuse impersonating another SaaS admin or a user without `company_id`.
   - `ImpersonationController@stop`: read stash, `Auth::login(originalAdmin)`, forget stash, redirect
     to `saas.companies`. Route guarded by `auth` only (the active user is the tenant during impersonation).
   - Sidebar/layout banner: when `session()->has('impersonator_id')`, show a Flux callout with
     "Stop impersonating" posting to `impersonate.stop`.
5. **Dashboard polish** — recent tenants table (last 10 by created_at with plan + status), trialing
   stat. Keep MRR/active/churn as-is. (Charts optional; skip to stay light unless trivial.)
6. **Routing & sidebar wiring** — register routes, add Plans nav item, render banner.
7. **Tests** — see Verification.
8. **Pint** — `vendor/bin/pint --dirty --format agent`.

## Verification / Testing Plan

`php artisan test --compact` plus targeted filters. New Pest feature tests:

- **SaasPlansTest:** saas admin can list/create/edit/deactivate; cannot delete a plan with
  subscriptions; non-saas user gets 403; slug uniqueness.
- **CompanyManagementTest:** edit contact details; suspend/reactivate toggles `suspended_at`;
  soft-delete sets `deleted_at` and hides from list; assigning a plan creates/updates the
  subscription and syncs `companies.plan_id`; changing status to `cancelled` blocks tenant access
  (assert `EnsureCompanyAdmin` 403 via a request as that company's user).
- **ImpersonationTest:** saas admin starts impersonation → now authenticated as the company user,
  session has `impersonator_id`, can reach `admin.dashboard`; stop restores the saas admin and clears
  stash; a non-saas user cannot start impersonation (403); cannot impersonate another saas admin.
- Tenant isolation: impersonation respects the company's spatie team id (permission checks resolve
  to the impersonated user's roles).

Then halt for manual user verification of the SaaS screens before the completion report.

## Risks & Assumptions

- **Impersonation auth swap** is the highest-risk piece. Mitigations: store only the original id in
  session; `stop` route on plain `auth`; never allow impersonating a SaaS admin; full re-auth via
  `Auth::login()` so guards/2FA state are correct. No remember-token regeneration needed.
- **Subscription as source of truth:** `companies.plan_id` and the latest `company_subscriptions`
  row can drift. The Show page writes both together; `EnsureCompanyAdmin` already trusts the
  subscription + trial, so keep that contract.
- **Soft-delete + unique slug:** soft-deleted companies keep their slug; the `uniqueSlug()` helper
  must consider `withTrashed()` to avoid unique-constraint collisions on re-create. Will verify the
  migration's unique index and adjust the helper.
- **Plan deletion** with existing subscriptions would orphan tenants → block delete, offer deactivate.
- **Activity log:** Company/SaasPlan/CompanySubscription already use `LogsActivity`; impersonation
  start/stop should also be logged manually (`activity()->log(...)`) for an audit trail.
- Charts on the SaaS dashboard are **optional** for this phase (idea.md lists "MRR, active tenants,
  churn" — all numeric stats already present). Will skip heavy charting unless trivial to add.

## Out of Scope (this phase)

- Restoring soft-deleted companies (admin UI) — deferred.
- SaaS-level Stripe billing automation (idea.md explicitly defers; plans are manually assigned).
- Per-company subdomain routing (deferred per idea.md).
