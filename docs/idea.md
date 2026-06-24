# WHMCS-Like Admin Panel — Project Idea

## Overview

Build a solid, production-ready **multi-tenant SaaS** hosting billing & client management panel inspired by WHMCS. The stack is **Laravel 13 + Livewire 4 + Flux UI v2 (Pro)** — no raw Blade tables, everything uses Flux components.

---

## SaaS Architecture

This is a **multi-tenant SaaS product**. There are two distinct user tiers:

### Tier 1 — SaaS Admin (`saas_admin`)
The platform owner. One account, global scope.

| Responsibility | Notes |
|---|---|
| Company (tenant) management | Create, suspend, delete companies |
| Subscription plans | Define SaaS plans (e.g. Starter, Pro, Agency) with feature limits |
| Subscription assignment | Assign/change a company's plan, set trial/expiry dates |
| Billing overview | See MRR, active tenants, churned companies |
| Impersonate any company | Log in as any company admin for debugging |
| Global settings | Platform-level SMTP, branding, feature flags |

**Credentials (seeded):** `admin@admin.com` / `admin@admin.com`

**Model:** Uses the existing `User` model with a boolean flag `is_saas_admin = true` (or a dedicated `saas_admin` role via spatie).

**Scope:** SaaS Admin has no access to individual company data (clients, invoices, tickets) except via impersonation.

---

### Tier 2 — Company Admins
Each **Company** (tenant) has one or more admin `User` accounts scoped to that company.

| Responsibility | Notes |
|---|---|
| Manage their own clients, services, invoices, tickets, domains | Fully isolated per company |
| Invite & manage sub-admins | Assign roles (Manager, Billing, Support, Read-only) within the company |
| Company settings | Their own SMTP, branding, invoice prefix, currency |

**Model:** `Company` — every tenant-scoped resource has a `company_id` foreign key.

---

### Tenancy Approach

- **Single database, `company_id` scoping** — simplest for v1; every query is scoped via a global scope or explicit `where('company_id', auth()->user()->company_id)`.
- No subdomain routing in v1 — all companies share the same URL; company is resolved from the authenticated user's `company_id`.
- Spatie roles/permissions are **scoped per company** using spatie's built-in team/guard support (`teams` feature enabled).

---

### URL Structure

```
/saas/*          → SaaS Admin area (company management, subscriptions, billing)
/admin/*         → Company admin area (clients, invoices, tickets, etc.)
```

Middleware:
- `saas_admin` middleware — protects `/saas/*`, checks `is_saas_admin`
- `company_admin` middleware — protects `/admin/*`, checks `company_id` is set and subscription is active

---

### SaaS Models

```
companies              → tenant records (name, slug, plan_id, trial_ends_at, suspended_at)
saas_plans             → subscription plan definitions (name, price, limits JSON)
company_subscriptions  → which plan a company is on, billing dates, status
```

---

## Core Modules

### 1. Clients
Manage customer accounts — the heart of the system.

| Feature | Notes |
|---|---|
| Client list with search & filters | Flux `flux:table`, filter by status/country |
| Create / Edit client | Name, email, phone, company, address, currency, language |
| Client profile page | Overview of services, invoices, support tickets |
| Client login-as | Admin can log in as any client for debugging |
| Client notes | Internal notes only admins can see |
| Client status | Active / Inactive / Closed |

**Models:** `Client` (separate from `User` admin accounts), `ClientNote`

---

### 2. Services / Products
What clients are subscribed to.

| Feature | Notes |
|---|---|
| Product groups | e.g. Shared Hosting, VPS, Domains, Email, SSL |
| Products | Name, description, pricing cycles (monthly/annual/etc), setup fee |
| Service instances | One per client subscription — tracks status, billing date, term |
| **Start & end dates** | Every service instance has `starts_at` and `expires_at` — drives renewals and reminders |
| **Expiry tracking** | Services nearing expiry surface in dashboard and reminder system |
| Service status | Active / Suspended / Cancelled / Pending / Expired |
| Service notes | Internal admin notes per service instance |

**Models:** `ProductGroup`, `Product`, `ProductPricing`, `ClientService`

> `ClientService` key date fields: `starts_at`, `expires_at`, `next_due_date`, `billing_cycle` (monthly/quarterly/semi-annual/annual/biennial/triennial/one-time).

---

### 3. Invoices & Billing
Generate, send, and record payments.

| Feature | Notes |
|---|---|
| Invoice list | Flux table with status badges (Unpaid / Paid / Overdue / Cancelled) |
| Auto-generate invoices | On service renewal due date |
| Manual invoice creation | Line items — description, qty, unit price, tax |
| Tax rates | Configurable per country or globally |
| Invoice PDF | Downloadable PDF (Laravel + DomPDF or Browsershot) |
| Record payment | Manual payment logging (cash, bank transfer, etc.) |
| Payment gateways | Modular: Stripe, PayPal (pluggable structure) |
| Credit notes | Negative invoices for refunds |

**Models:** `Invoice`, `InvoiceItem`, `Transaction`, `TaxRate`, `PaymentGateway`

---

### 4. Support Tickets
Lightweight internal helpdesk.

| Feature | Notes |
|---|---|
| Ticket list | Filter by status / department / priority / assigned admin |
| Departments | e.g. Sales, Technical, Billing |
| Ticket thread | Threaded replies, admin & client messages |
| Ticket status | Open / Answered / Customer-Reply / Closed |
| Priority | Low / Medium / High / Urgent |
| Ticket notes | Private internal notes (not visible to client) |
| Email piping (future) | Inbound email → auto-ticket (deferred) |
| File attachments | Attach screenshots / logs to replies |

**Models:** `TicketDepartment`, `Ticket`, `TicketReply`

---

### 5. Domains (Basic)
Track domain registrations — no live registrar API in v1.

| Feature | Notes |
|---|---|
| Domain list per client | Domain name, registrar, registration / expiry dates |
| **Start & expiry dates** | `registered_at` and `expires_at` — feeds reminder system |
| Domain status | Active / Expired / Pending Transfer / Cancelled |
| Manual renewal logging | Admin records renewal date + cost |
| Nameservers | Store NS1–NS4 |
| WHOIS notes | Free-text field |

**Models:** `Domain`

---

### 6. Expiry Reminders
Automated, configurable notifications sent before anything expires.

**What gets reminded:**

| Resource type | Trigger field | Examples |
|---|---|---|
| Hosting service | `client_services.expires_at` | Shared Hosting, VPS, Reseller |
| Email hosting | `client_services.expires_at` | G Suite, cPanel email |
| SSL certificate | `client_services.expires_at` | Let's Encrypt, Comodo |
| Domain registration | `domains.expires_at` | .com, .net, .in, etc. |
| Any custom product | `client_services.expires_at` | AMC, support contract, licence |

**How it works:**
- Admins define reminder rules per **product group** (or globally): e.g. "send at 30 days, 14 days, 7 days, 1 day before expiry"
- A scheduled Laravel command (`reminders:send`) runs daily, checks all `expires_at` values, and dispatches the correct notification
- Each reminder logs to `reminder_logs` so the same notice is never sent twice for the same resource + interval
- Admins can also manually trigger a reminder from the service/domain detail page

**Reminder channels (configurable per rule):**
- Email to client
- Email to admin (BCC or separate)
- In-app notification (Flux notification bell)

**Reminder rule fields:**
- `resource_type` — `service` | `domain` (or specific product group)
- `days_before` — integer, e.g. `30`, `14`, `7`, `1`
- `subject` — email subject template (supports `{client_name}`, `{product_name}`, `{expires_at}`, `{days_left}`)
- `body` — email body template (markdown)
- `notify_client` — bool
- `notify_admin` — bool
- `is_active` — enable/disable rule without deleting

**Models:** `ReminderRule`, `ReminderLog`

**Artisan command:** `php artisan reminders:send` — scheduled daily at 08:00

---

### 7. Admin Users, Roles & Permissions
Multiple admins per company with permission-based access control.

| Feature | Notes |
|---|---|
| Admin accounts | Separate from client accounts; use existing `User` model |
| Roles | e.g. Manager, Billing, Support, Read-only — defined per company |
| Permissions | Granular permission strings (e.g. `clients.view`, `invoices.create`) — all route/action gates check permissions, never roles directly |
| Role–Permission UI | Assign/sync permissions to roles via a dedicated admin screen |
| Admin activity log | Powered by `spatie/laravel-activitylog` — logs who did what, on which model, and when |

**Packages:**
- `spatie/laravel-permission` — roles & permissions with `hasPermissionTo()` checks on every gate/policy
- `spatie/laravel-activitylog` — replaces custom `activity_log` table; use `LogsActivity` trait on all key models

**Authorization rule:** Gates, policies, and middleware must check **permissions** (e.g. `$user->can('invoices.create')`), never role names. Role names are only used in the Role–Permission UI to group and assign permissions.

**Roles (default seed per company):**
- `manager` — full access within the company (all permissions)
- `billing` — invoices, payments, clients (read)
- `support` — tickets, clients (read)
- `read-only` — view-only across all modules

**Models:** Provided by `spatie/laravel-permission` (`Role`, `Permission`, pivot tables)

---

### 8. Dashboard
Single-page overview for admins on login.

| Metric | Widget |
|---|---|
| Total clients | Flux stat card |
| Active services | Flux stat card |
| Open tickets | Flux stat card |
| Revenue this month | Flux stat card |
| Recent invoices | Mini table |
| Recent tickets | Mini table |
| **Expiring soon (7 days)** | Warning list — services + domains combined |
| **Already expired** | Danger list — overdue renewals needing attention |
| Revenue chart | Monthly bar chart (last 6 months) |

---

### 9. Settings
System-wide configuration.

| Section | Fields |
|---|---|
| Company | Name, logo, address, email, phone |
| Billing | Default currency, tax label, invoice prefix, due-days |
| Email | SMTP config, from-name, from-email |
| Localisation | Date format, timezone, default language |
| Payment Gateways | Enable/disable, API key fields per gateway |

---

## UI & Frontend Conventions

- **All UI: Flux UI v2 (Pro)** — `flux:table`, `flux:modal`, `flux:input`, `flux:badge`, `flux:button`, `flux:tabs`, `flux:dropdown`, `flux:card`, `flux:navbar`, `flux:sidebar`
- **Layout:** Sidebar navigation (`x-layouts::app.sidebar`) already scaffolded — extend it
- **Forms:** Livewire 4 class-based components with real-time validation (`#[Validate]`)
- **Tables:** Flux `flux:table` with server-side pagination, search, and column sorting
- **Modals:** Flux `flux:modal` for create/edit/delete confirmations — no separate pages for simple CRUD
- **Badges:** Use `flux:badge` for status labels (color-coded: green=active, red=overdue, yellow=pending)
- **Tailwind v4** for any custom spacing/layout not covered by Flux

---

## Database Design (High Level)

```
users                  → admin accounts (existing); is_saas_admin flag for platform owner
companies              → tenant records (name, plan_id, trial_ends_at, suspended_at)
saas_plans             → SaaS subscription plan definitions (limits JSON)
company_subscriptions  → company ↔ plan assignment with billing dates & status
clients                → customer accounts
client_notes           → internal notes on clients
product_groups         → hosting plan categories
products               → plan definitions
product_pricings       → cycle/price rows per product
client_services        → client subscription instances (starts_at, expires_at, next_due_date)
invoices               → billing documents
invoice_items          → line items per invoice
transactions           → payment records
tax_rates              → configurable tax rules
payment_gateways       → gateway configs (encrypted keys)
ticket_departments     → helpdesk departments
tickets                → support threads
ticket_replies         → messages in a thread
domains                → domain registrations per client (registered_at, expires_at)
reminder_rules         → configurable reminder templates (resource_type, days_before, channels)
reminder_logs          → sent reminder history (prevents duplicate sends)
roles                  → spatie/laravel-permission (scoped per company/tenant)
permissions            → spatie/laravel-permission
model_has_roles        → spatie pivot: user ↔ role
model_has_permissions  → spatie pivot: user ↔ permission (direct)
role_has_permissions   → spatie pivot: role ↔ permission
activity_log           → spatie/laravel-activitylog (subject_type, causer_type, properties JSON)
settings               → key/value system settings
```

---

## Implementation Phases

### Phase 1 — Foundation & SaaS Structure ✅ Completed
- [x] Seed `admin@admin.com` / `admin@admin.com` as SaaS Admin (`is_saas_admin = true`) via `DatabaseSeeder`
- [x] `Company`, `SaasPlan`, `CompanySubscription` models + migrations
- [x] Install & configure `spatie/laravel-permission` with teams (company-scoped roles)
- [x] Install & configure `spatie/laravel-activitylog`
- [x] Default permission set seeded (e.g. `clients.view`, `clients.create`, `invoices.view`, etc.)
- [x] Default roles seeded per company: `manager`, `billing`, `support`, `read-only`
- [x] `saas_admin` middleware + `/saas/*` route group
- [x] `company_admin` middleware + `/admin/*` route group with `company_id` scoping
- [x] Sidebar nav for company admin area (all module links, inactive placeholders ok)
- [x] SaaS Admin area: company list + create company screen
- [x] Dashboard shell (stat cards, empty charts)

### Phase 2 — Clients ✅ Completed
- [x] `Client` model + migration + factory
- [x] Client list (Flux table, search, filter, pagination)
- [x] Create/Edit client modal
- [x] Client profile page
- [x] Client notes

### Phase 3 — Products & Services ✅ Completed
- [x] `ProductGroup`, `Product`, `ProductPricing` models
- [x] Products admin UI
- [x] `ClientService` model with `starts_at`, `expires_at`, `next_due_date`, `billing_cycle`
- [x] Assign service to client, manage status
- [x] Expiry date visible on service list with color-coded urgency

### Phase 4 — Invoices & Billing ✅ Completed
- [x] `Invoice`, `InvoiceItem`, `Transaction` models
- [x] Invoice list + create/edit
- [x] Record payment
- [x] Invoice PDF generation
- [x] Tax rate management

### Phase 5 — Support Tickets ✅ Completed
- [x] `TicketDepartment`, `Ticket`, `TicketReply` models
- [x] Ticket list with filters
- [x] Ticket thread view + reply form
- [x] Status & priority management

### Phase 6 — Domains ✅ Completed
- [x] `Domain` model with `registered_at`, `expires_at`
- [x] Domain list per client with expiry badge
- [x] Create/edit domain entry

> **Completed 2026-06-24** — `docs/completed/2026-06-24-1023-phase-6-domains.md`. Built the Domains module:
> `Domain` model (client-owned, tenant-isolated, soft-deletes, activity-logged) + `DomainStatus` enum
> (Active/Expired/Pending Transfer/Cancelled) with the `ClientService` expiry helpers
> (`isExpired`/`daysUntilExpiry`/`urgencyColor`). `/admin/domains` Flux screen with search, status + expiry
> filters, sortable columns, create/edit/delete modals, and a **dedicated Renew action** (stamps
> `last_renewed_at`, advances `expires_at`, reactivates expired domains). Status is **manual** in v1
> (auto-expiry deferred to Phase 7). `DomainPolicy` gates on the already-seeded `domains.*` permissions.
> Client profile gained a Domains stat + table card; sidebar Domains item enabled. 5 demo domains seeded.
> Tests: 154 passed (+16 new).

### Phase 7 — Expiry Reminders ✅ Completed
- [x] `ReminderRule` model + migration
- [x] `ReminderLog` model + migration
- [x] Reminder rules admin UI (create/edit/delete rules per resource type)
- [x] `php artisan reminders:send` command — daily scheduled job
- [x] Reminder email Mailable + Blade template (supports template variables)
- [x] Reminder log viewer (shows sent history per client/resource)
- [x] Manual "send reminder now" action from service/domain detail page

> **Completed 2026-06-24** — `docs/completed/2026-06-24-1148-phase-7-expiry-reminders.md`. Built the Expiry
> Reminders module: `ReminderRule` (tenant-scoped, soft-deletes, activity-logged) + `ReminderLog` (immutable
> audit/dedupe ledger with a `(remindable, days_before, channel)` unique key) + `ReminderResourceType` enum
> (Service | Domain). `/admin/reminders` Flux screen with a **Rules** CRUD tab and a **Sent Log** tab. A
> daily **`reminders:send`** command (scheduled 08:00) scans every tenant's `expires_at`, dispatches deduped
> reminders via a shared `ReminderDispatcher`, and **auto-expires** lapsed Active services/domains (the
> Phase 6 deferral). Queued `ExpiryReminderMail` + markdown template with `{client_name}`,
> `{product_name}`/`{domain_name}`, `{expires_at}`, `{days_left}` substitution. A **"Send reminder"** row
> action on Services and Domains forces a send; renewing a domain (or editing a service's expiry) clears its
> logs to re-arm the next cycle. Rules scoped **global per company** by resource type (v1); admin copy goes
> to **`Company.email`**. `ReminderRulePolicy` gates on the already-seeded `reminders.*` permissions; sidebar
> Reminders item enabled; 8 default rules seeded per company. Tests: 174 passed (+20 new).

### Phase 8 — Settings & Polish
- [ ] Settings key/value store
- [ ] Company / billing / email / gateway settings UI
- [ ] Reminder settings section (default lead times, global on/off)
- [ ] Dashboard charts wired to real data
- [ ] Dashboard expiry widgets wired to real data
- [ ] **Role–Permission UI** — Livewire screen at `/admin/roles` to create roles, list all permissions, and sync permissions to roles (checkbox matrix)
- [ ] Admin activity log viewer (powered by spatie/laravel-activitylog)

### Phase 9 — SaaS Admin Area
- [ ] SaaS Admin dashboard (MRR, active tenants, churn)
- [ ] Company management (create, suspend, delete, impersonate)
- [ ] SaaS plan management (define plans with feature limits)
- [ ] Subscription assignment (assign plan to company, set trial/expiry)
- [ ] Company impersonation (log in as any company admin)

---

## Default Seed Data

```php
// DatabaseSeeder
User::create([
    'name'          => 'Admin',
    'email'         => 'admin@admin.com',
    'password'      => 'admin@admin.com',  // hashed via 'hashed' cast
    'is_saas_admin' => true,
]);
```

Sample data for development (scoped to a seeded demo `Company`):
- 10 fake clients
- 3 product groups, 8 products
- 20 invoices (mix of paid/unpaid/overdue)
- 15 support tickets
- 5 domains
- 1 demo company with all default roles and permissions assigned

---

## Key Technical Decisions

| Decision | Choice | Reason |
|---|---|---|
| Auth | Laravel Fortify (already installed) | Handles login, 2FA, passkeys |
| UI components | Flux UI v2 Pro (already installed) | Consistent, production-quality UI |
| Reactivity | Livewire 4 class-based components | Project convention |
| PDF invoices | `barryvdh/laravel-dompdf` (to install) | Lightweight, no Node dependency |
| Charts | Flux-compatible Alpine.js + Chart.js CDN | No extra package needed |
| RBAC | `spatie/laravel-permission` | Permission-based gates (not role-based); Role–Permission UI for admins |
| Activity log | `spatie/laravel-activitylog` | Model-level audit trail; `LogsActivity` trait on key models |
| Email | Laravel Mail + queue | Standard Laravel pattern |
| Encryption | Laravel `encrypt()` for gateway API keys | Built-in, no extra package |
| Reminders | Laravel Scheduler + Mail + queue | `reminders:send` runs daily; queued Mailables prevent timeout |

---

## Out of Scope (v1)

- Live domain registrar API (Namecheap, Enom)
- Automated service provisioning (cPanel API, Plesk)
- Client portal (client-facing login) — admin-only panel first
- Reseller / affiliate system
- Crypto payment gateways
- Multi-language / i18n UI
- SaaS billing automation (Stripe for SaaS subscriptions) — manual plan assignment in v1
- Per-company subdomain routing — single URL, company resolved from user's `company_id`
