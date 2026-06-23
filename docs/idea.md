# WHMCS-Like Admin Panel — Project Idea

## Overview

Build a solid, production-ready hosting billing & client management admin panel inspired by WHMCS. The stack is **Laravel 13 + Livewire 4 + Flux UI v2 (Pro)** — no raw Blade tables, everything uses Flux components.

Default superadmin credentials: `admin@admin.com` / `admin@admin.com` (seeded on first run).

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

### 7. Admin Users & Roles
Multiple admins with role-based access.

| Feature | Notes |
|---|---|
| Admin accounts | Separate from client accounts; use existing `User` model |
| Roles | Superadmin, Billing, Support, Read-only |
| Permissions | Per-role permission gates (Laravel policies/gates) |
| Admin activity log | Who did what and when |

**Models:** `Role`, `Permission` (or use a lightweight custom RBAC — no spatie unless needed)

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
users                  → admin accounts (existing)
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
roles                  → admin role definitions
role_user              → pivot: admin ↔ role
activity_log           → admin audit trail
settings               → key/value system settings
```

---

## Implementation Phases

### Phase 1 — Foundation
- [ ] Seed `admin@admin.com` / `admin@admin.com` superadmin via `DatabaseSeeder`
- [ ] Admin role & permission gates
- [ ] Sidebar nav with all module links (inactive placeholders ok)
- [ ] Dashboard shell (stat cards, empty charts)

### Phase 2 — Clients
- [ ] `Client` model + migration + factory
- [ ] Client list (Flux table, search, filter, pagination)
- [ ] Create/Edit client modal
- [ ] Client profile page
- [ ] Client notes

### Phase 3 — Products & Services
- [ ] `ProductGroup`, `Product`, `ProductPricing` models
- [ ] Products admin UI
- [ ] `ClientService` model with `starts_at`, `expires_at`, `next_due_date`, `billing_cycle`
- [ ] Assign service to client, manage status
- [ ] Expiry date visible on service list with color-coded urgency

### Phase 4 — Invoices & Billing
- [ ] `Invoice`, `InvoiceItem`, `Transaction` models
- [ ] Invoice list + create/edit
- [ ] Record payment
- [ ] Invoice PDF generation
- [ ] Tax rate management

### Phase 5 — Support Tickets
- [ ] `TicketDepartment`, `Ticket`, `TicketReply` models
- [ ] Ticket list with filters
- [ ] Ticket thread view + reply form
- [ ] Status & priority management

### Phase 6 — Domains
- [ ] `Domain` model with `registered_at`, `expires_at`
- [ ] Domain list per client with expiry badge
- [ ] Create/edit domain entry

### Phase 7 — Expiry Reminders
- [ ] `ReminderRule` model + migration
- [ ] `ReminderLog` model + migration
- [ ] Reminder rules admin UI (create/edit/delete rules per resource type)
- [ ] `php artisan reminders:send` command — daily scheduled job
- [ ] Reminder email Mailable + Blade template (supports template variables)
- [ ] Reminder log viewer (shows sent history per client/resource)
- [ ] Manual "send reminder now" action from service/domain detail page

### Phase 8 — Settings & Polish
- [ ] Settings key/value store
- [ ] Company / billing / email / gateway settings UI
- [ ] Reminder settings section (default lead times, global on/off)
- [ ] Dashboard charts wired to real data
- [ ] Dashboard expiry widgets wired to real data
- [ ] Admin activity log

---

## Default Seed Data

```php
// DatabaseSeeder
User::create([
    'name'     => 'Admin',
    'email'    => 'admin@admin.com',
    'password' => 'admin@admin.com',  // hashed via 'hashed' cast
]);
```

Sample data for development:
- 10 fake clients
- 3 product groups, 8 products
- 20 invoices (mix of paid/unpaid/overdue)
- 15 support tickets
- 5 domains

---

## Key Technical Decisions

| Decision | Choice | Reason |
|---|---|---|
| Auth | Laravel Fortify (already installed) | Handles login, 2FA, passkeys |
| UI components | Flux UI v2 Pro (already installed) | Consistent, production-quality UI |
| Reactivity | Livewire 4 class-based components | Project convention |
| PDF invoices | `barryvdh/laravel-dompdf` (to install) | Lightweight, no Node dependency |
| Charts | Flux-compatible Alpine.js + Chart.js CDN | No extra package needed |
| RBAC | Custom gates/policies | Keep dependencies minimal |
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
