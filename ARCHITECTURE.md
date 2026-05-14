# Architecture — Zen Retreats Portal

A short, opinionated overview of how this codebase is structured and why. Pair with the Discovery & Requirements document (in the parent workspace folder) for the business context.

## High-level shape

A single Laravel 11 application that serves both the staff portal (Phase 1) and the client self-service portal (Phase 2) from one codebase, behind one authentication layer with role-based access control.

```
Browser ─Inertia─► Laravel HTTP ─► Controllers ─► Services ─► Models ─► Postgres
                          │
                          ├─ Queue jobs ─► Horizon worker ─► (Notifications, CRM sync, statements)
                          ├─ Audit events ─► audit_events table (append-only)
                          └─ Activity log ─► activity_log table (Spatie)
```

## Domain boundaries

| Module | Owner concept | Lives in |
|---|---|---|
| Inventory | Project → Block → Unit, Unit Categories, Plan Templates | `app/Models/{Project,Block,Unit,UnitCategory,PlanTemplate}.php` + `app/Http/Controllers/Inventory/*` |
| Clients & KYC | Client, Nominee, document vault | `app/Models/{Client,Nominee,Document}.php` |
| Bookings | Booking, co-buyers, instantiated schedule | `app/Models/{Booking,BookingCoBuyer,Schedule}.php` |
| Payments | Payment, Allocation (FIFO default), Adjustment | `app/Models/{Payment,Allocation,Adjustment}.php` |
| Reconciliation | BankStatementImport → BankStatementLine → match queue | `app/Models/BankStatement*.php` |
| Statements | Generated PDFs stored as Document records | (Phase 1 build) |
| Notifications | Templates + log + multi-channel adapters | `app/Models/{NotificationTemplate,NotificationLog}.php` |
| CRM integration | CrmLink (mapping) + CrmSyncJob (retry queue) | `app/Models/CrmLink.php` |
| Audit | Append-only AuditEvent + Spatie activity_log | `app/Models/AuditEvent.php` + `app/Services/AuditEventWriter.php` |
| Settings | Key-value with type metadata | `app/Models/Setting.php` |

## Decisions and rationale

### Money is stored in minor units (paisa)

Every monetary column is `bigint` named `*_minor`. Float dollars are never persisted. UI inputs in major units (rupees) flow through `money_major_to_minor()` (in `app/helpers.php`) on the way in, and `money_minor_to_major()` on the way out. This eliminates a whole class of rounding bugs in financial code.

### Single Payment table with channel enum

The old portal had `cash_payments` and `bank_payments` as separate tables. We collapse them into one `payments` table with a `channel` enum (`PaymentChannel` in `app/Enums`). This makes statements, reconciliation, and reporting one query instead of several.

### Allocations are first-class

A `Payment` carries the funds; an `Allocation` records which scheduled item(s) the payment was applied against. A single payment can settle many schedules, and a schedule can be settled by many partial payments. Default allocation algorithm is FIFO against the oldest open due (to be implemented in `app/Services/AllocationService.php`).

### Outstanding balance is computed, never stored

We don't keep an `outstanding_minor` field on Booking. Instead `Booking::outstandingMinor()` computes it from schedules − allocations − credit adjustments + debit adjustments. The old `user_balances` table is gone. This was the single biggest source of drift in the previous portal.

### Audit lives in two places

- `activity_log` (Spatie) captures all entity changes (who edited what, when). Use this for "show me the history of this booking".
- `audit_events` is hand-written for **financially impactful** events (payment posted, payment reversed, late fee waived, plan amended) — append-only, with before/after snapshots, IP, and user agent. Updating or deleting an `AuditEvent` row throws a `LogicException`. This is what auditors look at.

### Sensitive PII is encrypted at rest

CNIC and passport numbers use Laravel's `encrypted` cast. We also store a HMAC hash (`cnic_hash`) so we can find duplicates without ever seeing the plaintext. Decryption only happens at the model layer for callers with the right role; no plaintext flows through logs or API responses.

### Auth model

- `User` is the login record. A user with `client_id IS NULL` is staff; otherwise they're a client.
- Roles via `spatie/laravel-permission` — see `App\Enums\RoleName` for the canonical list.
- Permissions are dotted (`payments.post`, `adjustments.approve`) and granted to roles; Policies wrap them per model.
- Super admin bypasses all gates via `Gate::before` in `AuthServiceProvider`.

### Frontend

Inertia + Vue 3 + Tailwind. No Laravel-side Blade for the portal UI — `app.blade.php` is just the SPA host. Pages live under `resources/js/Pages/`, grouped by module. Layouts under `resources/js/Layouts/`. Shared components under `resources/js/Components/`.

### Queue & schedule

Everything that takes longer than a request (PDF generation, email/SMS sends, CRM sync, statement runs, reconciliation suggestions) goes through Laravel Queues on Redis. Horizon supervises workers and provides a dashboard. `routes/console.php` is where scheduled commands register; the scheduler is invoked once per minute by a single cron entry.

## Phase 1 build order

1. ✅ Foundation — auth, RBAC, audit middleware, Inertia plumbing, layouts
2. ✅ Inventory — Projects, Blocks, Unit Categories, Units (CRUD end-to-end)
3. **Clients & KYC** — `ClientController`, KYC verification flow, document vault
4. **Bookings** — booking form, plan instantiation into Schedule rows, co-buyers
5. **Payments + allocations** — record payment, allocate FIFO, generate receipt PDF
6. **Reconciliation** — bank-statement CSV import, suggested matches, confirm queue
7. **Statements** — branded PDF (DomPDF), monthly cron, on-demand
8. **Notifications** — template engine + email + SMS adapters + reminder/dunning schedules
9. **Finance dashboard** — collections this week, unmatched bank lines, overdue queue

Phase 2 then layers on:

10. Client self-service login + dashboard + statement view + proof upload
11. GenieCentral two-way sync + webhook receiver + retry queue
12. WhatsApp Business channel

## Naming conventions

- DB tables: plural snake_case (`bookings`)
- Models: singular PascalCase (`Booking`)
- Controllers: `<Entity>Controller` (singular)
- FormRequests: `<Entity>Request`
- Policies: `<Entity>Policy`
- Routes: kebab-case (`/inventory/unit-categories`)
- Vue pages: PascalCase matching the route (`Inventory/UnitCategories/Index.vue`)
- Tests: feature tests mirror the controller layout

## Don'ts

- Don't add an `outstanding_minor` column to Booking — it will drift.
- Don't hard-delete a Payment — set `status = reversed` with a reason.
- Don't use floats for money. Anywhere.
- Don't bypass `Gate::authorize`. Every controller action checks.
- Don't write to `audit_events` outside `AuditEventWriter::record()`.
- Don't run with `APP_DEBUG=true` outside local dev.
