# Phase 7 — Expiry Reminders (Plan)

**Date:** 2026-06-24 11:48
**Phase:** 7 of 9 (`docs/idea.md` §6, §Implementation Phases)
**Status:** 📝 Planned — awaiting implementation

---

## Goal

Deliver the **Expiry Reminders** module: automated, configurable notifications sent before a
**client service** or a **domain** expires. Admins define reminder rules (resource type + days-before +
channels + email templates); a daily scheduled command (`reminders:send`) scans every `expires_at`,
dispatches the correct queued email, and logs each send to `reminder_logs` so the same notice is never
sent twice for the same resource + interval. Admins can also trigger a reminder manually, and view the
sent-reminder history. The same command also **auto-expires** services/domains whose `expires_at` has
passed.

---

## Clarified Decisions (from user, 2026-06-24)

1. **Rule scope — global per company.** Rules target by `resource_type` (`service` | `domain`) only.
   No per-product-group targeting in v1 (deferred); keeps the core `idea.md` field set
   (`resource_type`, `days_before`, …).
2. **Mail delivery — real queued Mailable.** Build a queued `ExpiryReminderMail` + Blade template with
   template-variable substitution; sent via the configured mailer (locally the `log` mailer, so nothing
   leaves). Tests use `Mail::fake()`.
3. **Auto-expiry — included.** The daily command flips `Active` services/domains whose `expires_at` has
   passed to `Expired`, fulfilling the Phase 6 deferral. Logged via the existing activity log.
4. **Admin copy recipient — `Company.email`.** When a rule has `notify_admin`, the admin copy goes to the
   tenant's `Company.email` contact address (single recipient).

---

## Affected Items

### New files

- `app/Enums/ReminderResourceType.php` — `Service` | `Domain` (`label()`, helpers).
- `app/Models/ReminderRule.php` — tenant-scoped rule (`BelongsToCompany`, `LogsActivity`, soft-deletes).
- `app/Models/ReminderLog.php` — tenant-scoped sent-history row (no soft-deletes; immutable audit).
- `database/migrations/xxxx_create_reminder_rules_table.php`
- `database/migrations/xxxx_create_reminder_logs_table.php`
- `database/factories/ReminderRuleFactory.php`
- `database/factories/ReminderLogFactory.php`
- `database/seeders/ReminderRuleSeeder.php` — seeds a default rule set for the demo company.
- `app/Policies/ReminderRulePolicy.php` — gates on the already-seeded `reminders.view` / `reminders.manage`.
- `app/Livewire/Admin/Reminders/Index.php` + `resources/views/livewire/admin/reminders/index.blade.php`
  — rules CRUD (Flux table + modals) **and** the log viewer (a tab or second card).
- `app/Console/Commands/SendExpiryReminders.php` — `reminders:send`.
- `app/Mail/ExpiryReminderMail.php` — queued Mailable.
- `resources/views/mail/expiry-reminder.blade.php` — markdown email template.
- `app/Support/ReminderDispatcher.php` *(action/service class)* — the reusable "evaluate rules → send →
  log" engine, called by both the command and the manual "send now" action so logic is not duplicated.
- `tests/Feature/RemindersTest.php` — rules CRUD + RBAC + dispatcher + command + auto-expiry + manual send.

### Changed files

- `routes/web.php` — register `admin.reminders` Livewire route.
- `resources/views/layouts/app/sidebar.blade.php` — enable the **Reminders** nav item (currently
  `:disabled="true"`), gate on `reminders.view`.
- `routes/console.php` *(or a scheduler registration)* — schedule `reminders:send` daily at 08:00.
- `app/Livewire/Admin/Services/Index.php` + view — add a per-row **"Send reminder now"** action
  (gated on `reminders.manage`).
- `app/Livewire/Admin/Domains/Index.php` + view — same per-row manual reminder action.
- `database/seeders/DatabaseSeeder.php` — call `ReminderRuleSeeder` after `DomainSeeder`.
- `docs/idea.md` — mark Phase 7 complete with a summary + completion-report reference.

### Database

```
reminder_rules
  id, company_id (FK cascade), resource_type (string: service|domain),
  days_before (unsigned int), subject (string), body (text),
  notify_client (bool, default true), notify_admin (bool, default false),
  is_active (bool, default true), timestamps, softDeletes
  index(company_id), index(['resource_type','is_active'])

reminder_logs
  id, company_id (FK cascade), reminder_rule_id (nullable FK nullOnDelete),
  remindable_type + remindable_id (morphs — ClientService | Domain),
  client_id (FK, for the log viewer), days_before (unsigned int),
  channel (string: client|admin), recipient (string email),
  sent_at (timestamp), timestamps
  unique(['remindable_type','remindable_id','days_before','channel']) — the dedupe key
  index(company_id), index(client_id)
```

> **Dedupe key:** `(remindable_type, remindable_id, days_before, channel)`. A given resource gets each
> interval's client notice and admin notice at most once, ever. (Renewing a domain pushes `expires_at`
> out; the next cycle's interval rows are new resource+interval combos only if the old log rows are
> cleared on renew — see Risks.)

---

## Implementation Steps

1. **Enum** — `ReminderResourceType` (Service/Domain, `label()`).
2. **Migrations** — `reminder_rules`, `reminder_logs` as specced above. Use `morphs('remindable')`.
3. **Models**
   - `ReminderRule`: `BelongsToCompany`, `LogsActivity`, `SoftDeletes`; casts (`resource_type` enum,
     bool flags); `getActivitylogOptions()`. `What/Why/When` PHPDoc on the class.
   - `ReminderLog`: `BelongsToCompany`; `remindable()` morphTo, `client()` belongsTo, `rule()` belongsTo;
     `sent_at` datetime cast. No activity log (it *is* the audit record).
4. **Factories** for both; **`ReminderRuleSeeder`** seeding a sensible default set per company
   (e.g. service + domain rules at 30 / 14 / 7 / 1 days, `notify_client` on).
5. **Policy** — `ReminderRulePolicy` (`viewAny`/`view` → `reminders.view`; `create`/`update`/`delete` →
   `reminders.manage`). Auto-discovered by the `Model→Policy` convention (no provider change), per the
   Phase 6 note.
6. **Template engine** — a small helper that substitutes `{client_name}`, `{product_name}` /
   `{domain_name}`, `{expires_at}`, `{days_left}` in subject/body. Centralise so the Mailable, the
   command preview, and tests share it.
7. **`ExpiryReminderMail`** — `ShouldQueue`, markdown view `mail.expiry-reminder`, takes the rendered
   subject + body + resource context.
8. **`ReminderDispatcher`** (action class) — the engine:
   - For one company (or all), load active rules per `resource_type`.
   - For each rule, find resources whose `daysUntilExpiry() === days_before` (computed via a
     `whereDate('expires_at', today + days_before)` query, **bypassing the tenant global scope** with
     `withoutGlobalScopes()` since the console has no auth context — mirrors the seeders/factories).
   - Skip any (resource, days_before, channel) already in `reminder_logs` (dedupe).
   - If `notify_client`: mail the client's email; if `notify_admin`: mail `company->email`.
   - Insert a `reminder_log` row per channel actually sent.
   - Return a small summary (counts) for command output / toast.
9. **`reminders:send` command** — iterate companies, call the dispatcher, then run **auto-expiry**
   (flip `Active` → `Expired` where `expires_at < today` for both `ClientService` and `Domain`,
   `withoutGlobalScopes()`, logged via the activity log). Print a compact summary.
10. **Schedule** — register `reminders:send` daily at 08:00 in `routes/console.php`
    (`Schedule::command('reminders:send')->dailyAt('08:00')`).
11. **Reminders Livewire screen** (`/admin/reminders`) — Flux table of rules (resource type, days_before,
    channels, active badge) with create/edit/delete modals (gated on `reminders.manage`); a **log viewer**
    (second tab/card) listing recent `reminder_logs` with client, resource, interval, channel, sent_at.
    `mount()` authorizes `viewAny`.
12. **Manual "send reminder now"** — add a row action on the Services and Domains lists that calls the
    dispatcher for that single resource across all matching active rules (gated `reminders.manage`),
    bypassing the days_before match (admin-forced) but still respecting/writing dedupe? → **forced send
    ignores dedupe and writes a log with `days_before = daysUntilExpiry()`** (see Risks for the decision).
13. **Routes + sidebar** — register route; enable sidebar item gated on `reminders.view`.
14. **Seeder wiring** — `DatabaseSeeder` calls `ReminderRuleSeeder`.
15. **Tests** (Pest) — see Testing.
16. **`vendor/bin/pint --dirty`**, then run the suite.

---

## Verification / Testing Plan

`php artisan test --compact --filter=Reminders` plus a full run. Cases:

- **Tenant isolation** — rules/logs of company B invisible to company A; `company_id` auto-stamped on
  rule create via the Livewire form.
- **RBAC** — list 403 without `reminders.view`; create/edit/delete 403 without `reminders.manage`;
  manual "send now" 403 without `reminders.manage`.
- **Rule CRUD + validation** — `resource_type` required/enum, `days_before` ≥ 0 integer, subject/body
  required; edit persists; delete soft-deletes (hidden from list).
- **Template substitution** — `{client_name}`/`{product_name}`/`{domain_name}`/`{expires_at}`/`{days_left}`
  resolve correctly for both resource types.
- **Dispatcher / command** — with `Mail::fake()`: a service/domain expiring in exactly N days where an
  active N-day rule exists ⇒ correct Mailable queued to the **client** (and to `company->email` when
  `notify_admin`); a `reminder_log` row written; **second run sends nothing** (dedupe); inactive rules and
  non-matching intervals send nothing; `notify_client=false` skips the client mail.
- **Auto-expiry** — `Active` service & domain past `expires_at` flip to `Expired` after the command;
  future-dated ones untouched; already-`Cancelled`/`Expired` untouched.
- **Manual send** — forces a mail + log for one resource regardless of interval; gated on `reminders.manage`.

---

## Risks & Assumptions

- **Console has no tenant context.** `BelongsToCompany`'s global scope no-ops without an authenticated
  company admin, so the dispatcher/command must scope **explicitly** per company and use
  `withoutGlobalScopes()` where it reads across tenants — exactly the seeder/factory pattern already in
  the codebase. This is the single biggest correctness risk; tests cover multi-company runs.
- **Dedupe vs. renewal.** The dedupe unique key prevents re-sending the *same* interval for the *same*
  resource. After a renewal pushes `expires_at` out, the resource is the same row, so its old
  `(resource, days_before, channel)` logs would block a fresh cycle's notices. **Decision:** on
  **renewal** (domain `renew()`, and service expiry edits), delete that resource's `reminder_logs` so the
  new cycle re-arms. This will be a small addition to the existing Domains `renew()` and the Services
  update path. Flagged here; will confirm during implementation.
- **Manual "send now" + dedupe.** A forced admin send ignores the dedupe check (admin explicitly asked)
  and writes a log row with `days_before = max(daysUntilExpiry(), 0)`; if a row already exists for that
  combo we update `sent_at` rather than violating the unique index.
- **Queue not running locally.** `ShouldQueue` Mailables need a worker; with the default `sync`/`database`
  queue and `log` mailer, local sends are harmless. Tests assert via `Mail::assertQueued`.
- **`company->email` may be null.** If a tenant has no contact email, the admin copy is skipped (and not
  logged) rather than erroring.
- **No per-product-group targeting** (deferred) — rules are company-global by `resource_type`.
- **Scheduler must be running** (`php artisan schedule:work` / cron) for the daily job; out of scope to
  configure production cron here — only the schedule registration is added.

---

## Discovery Notes

- `reminders.view` / `reminders.manage` permissions are **already seeded** in
  `RolesAndPermissionsSeeder::PERMISSIONS` (granted to `manager`; `read-only` gets `reminders.view`). No
  permission catalog change needed — same situation as `domains.*` in Phase 6.
- The sidebar already has a **disabled** Reminders item (`bell-alert` icon) to enable.
- `ClientService` and `Domain` both expose `isExpired()` / `daysUntilExpiry()` / `urgencyColor()` and an
  indexed `expires_at` — the dispatcher reuses these; no model changes needed for the scan itself.
- This phase introduces the app's **first** `app/Console/Commands`, `app/Mail`, and `app/Support`
  directories — created under the standard Laravel structure (no new top-level base folders).
- No sub-agents needed; the codebase is small and the conventions are now fully mapped.
