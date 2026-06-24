# Phase 9 — SaaS Admin Area — Completion Report

**Date:** 2026-06-24
**Plan:** `docs/plans/2026-06-24-1538-phase-9-saas-admin-area.md`
**Status:** ✅ Completed (user-verified)

## Summary

Delivered all five Phase 9 items from `docs/idea.md`, completing the **Tier 1 (SaaS Admin)**
platform-owner experience:

1. **SaaS dashboard** — added a *Trialing* stat card and a real **Recent Tenants** table (last 10 by
   creation, with plan + status badges and per-row Manage links). The existing live Active Tenants,
   Churned Tenants, and MRR metrics were retained; the placeholder "charts later" card was replaced.
2. **Company management** — a new company detail screen handles contact-detail edits,
   suspend/reactivate, and **soft-delete** behind a typed-name confirmation modal. The companies list
   now links each row to it. (Create + list-level suspend already existed from Phase 1.)
3. **SaaS plan management** — a full Plans CRUD screen (`/saas/plans`): create/edit/delete tiers with
   price, interval, and `limits` (max clients / max admins, blank = unlimited), an active toggle, and
   unique slug generation. Deleting a plan that has subscriptions is blocked with a toast directing
   the admin to deactivate instead.
4. **Subscription assignment** — on the company detail page: assign/change plan, set status
   (trialing / active / past_due / cancelled), `starts_at`, `ends_at`, and `trial_ends_at`. Writes
   the `company_subscriptions` row (create-or-update, never duplicated) and keeps `companies.plan_id`
   in sync as the single source of truth that `EnsureCompanyAdmin` already trusts.
5. **Company impersonation** — a SaaS admin logs in as a chosen company admin; a persistent banner
   with "Stop impersonating" restores the original identity. Refuses to impersonate another SaaS
   admin or a user without a company; both start and stop are written to the activity log.

Sub-agents were **not** used; the work was implemented directly after inspecting the existing SaaS
models, the `Companies\Index` / `TaxRates\Index` components, the sidebar, and the test helpers.

## Changelog

### Added
- `app/Services/Saas/Impersonation.php` — encapsulates the auth swap + session stash; `start()`,
  `stop()`, `isImpersonating()`, and the `SESSION_KEY` constant. Activity-logged.
- `app/Http/Controllers/Saas/ImpersonationController.php` — thin `start`/`stop` actions over the
  service.
- `app/Livewire/Saas/Plans/Index.php` + `resources/views/livewire/saas/plans/index.blade.php` —
  SaaS plan CRUD with limits, active toggle, unique slugs, and the subscription-guarded delete.
- `app/Livewire/Saas/Companies/Show.php` + `resources/views/livewire/saas/companies/show.blade.php`
  — company detail: contact edit, subscription assignment, suspend/reactivate, impersonate picker,
  typed-name soft-delete.
- `tests/Feature/Saas/SaasPlansTest.php` — plans access + CRUD (incl. delete-guard, slug uniqueness).
- `tests/Feature/Saas/CompanyManagementTest.php` — detail access, edit, suspend, soft-delete,
  subscription assign/update, and cancellation → tenant lockout.
- `tests/Feature/Saas/ImpersonationTest.php` — start/stop, tenant reach, and the three refusal guards.

### Changed
- `app/Models/Company.php` — added the `subscriptions(): HasMany` history relation.
- `app/Livewire/Saas/Dashboard.php` — added `trialingTenants()` and `recentCompanies()` computeds.
- `resources/views/livewire/saas/dashboard.blade.php` — swapped the Total-Companies card for a
  Trialing card and the placeholder card for the Recent Tenants table.
- `resources/views/livewire/saas/companies/index.blade.php` — added a per-row "Manage" link.
- `routes/web.php` — registered `saas.companies.show`, `saas.plans`, `saas.impersonate`, and (outside
  the `saas_admin` group) `impersonate.stop`.
- `resources/views/layouts/app/sidebar.blade.php` — added the **Plans** nav item and the persistent
  impersonation banner.
- `tests/Pest.php` — added the `saasAdmin()` global test helper.
- `docs/idea.md` — marked Phase 9 complete with a summary.

### Deleted
- None.

## Validation

- **Automated:** `php artisan test --compact` → **226 passed**, 528 assertions (201 baseline + 25 net
  new in `tests/Feature/Saas/`).
- **Formatting:** `vendor/bin/pint --dirty --format agent` → clean (one test file auto-grouped imports).
- **Routing:** `saas.plans`, `saas.companies.show`, `saas.impersonate`, and `impersonate.stop` all
  resolve (asserted via the access/redirect tests).
- **Bug fixed during testing:** the "cancellation blocks the tenant" test initially returned 200 on
  the second request because `EnsureCompanyAdmin` lazy-loads and caches `$user->company` on the
  in-memory user instance reused by `actingAs`. Fixed by re-fetching the user (`->fresh()`) for the
  post-cancellation request; production behaviour was already correct (verified via tinker).
- **Manual:** User verified Plans CRUD (incl. the delete guard), the company detail edit/subscription/
  suspend/delete flows, and the impersonate → banner → stop round-trip.

## Postponed Items

- **Restoring soft-deleted companies** — deletion is reversible at the data layer (SoftDeletes) but
  there is no admin UI to list/restore trashed tenants yet.
- **SaaS-level billing automation** — plans are assigned manually; Stripe-for-SaaS is explicitly out
  of scope for v1 per `docs/idea.md`.
- **Plan `limits` enforcement** — `max_clients` / `max_admins` are stored and displayed but not yet
  enforced at tenant resource-creation time.
- **SaaS dashboard charts** — the numeric MRR/active/churn/trialing stats are live; a visual
  revenue/churn chart was deliberately deferred (idea.md only specifies the metrics).

## Follow-up Recommendations

- Enforce plan `limits` when a tenant creates clients/admins (block or warn at the capacity ceiling).
- Add a "Trashed companies" filter + restore action on the companies list.
- Consider regenerating the session id on impersonation start/stop for defence-in-depth (the current
  flow re-authenticates via `Auth::login()`, which is sufficient but not session-fixation-hardened).
- With Phase 9 done, the core v1 scope from `docs/idea.md` is complete — next work is the deferred
  follow-ups across phases (live mail/gateway wiring, limits enforcement, charting).
```
