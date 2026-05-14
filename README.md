# Zen Retreats — Client Portal

Real-estate client management portal for Zen Retreats (Barian, Murree). Source of truth for clients, units, installment plans, payments, statements, reconciliation, and automated reminders. Integrates with the GenieCentral CRM.

Background and requirements: see `Zen_Retreats_Portal_Discovery_Requirements_v1.0.docx` in the parent workspace folder.

## Stack

- **Backend:** Laravel 11 (PHP 8.3)
- **Frontend:** Inertia.js + Vue 3 + Tailwind CSS, built with Vite
- **DB:** PostgreSQL 16 (MySQL 8 supported)
- **Cache / queue / session:** Redis 7
- **Workers:** Laravel Horizon
- **Auth:** Laravel Sanctum + spatie/laravel-permission for RBAC
- **Audit:** spatie/laravel-activitylog
- **PDF:** barryvdh/laravel-dompdf (Browsershot upgrade in Phase 3 if needed)
- **Storage:** local at dev; S3-compatible in production

## Quick start (local dev)

```bash
# 1. Clone + install
git clone <repo>
cd zen-retreats-portal
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Bring up Postgres + Redis (Docker)
docker compose up -d postgres redis

# 4. Database
php artisan migrate --seed

# 5. Run dev servers (two terminals)
php artisan serve            # http://localhost:8000
npm run dev                  # Vite HMR

# 6. Workers (optional in dev)
php artisan horizon          # queue workers + scheduler
```

Default seeded login:

- **Super Admin:** `admin@zenretreats.local` / `password` (change immediately in any real environment)

## Project layout

```
app/
  Console/Commands/      # artisan commands (reminders, reconciliation, statements)
  Enums/                 # PHP 8 enums (PaymentChannel, ScheduleStatus, etc.)
  Http/
    Controllers/         # web controllers, grouped by module
      Auth/              # login, password reset, 2FA
      Inventory/         # projects, blocks, units, plans
    Middleware/
    Requests/            # form-request validation, grouped by module
    Resources/           # API resources (JSON)
  Models/                # Eloquent models
  Policies/              # authorization policies (one per model)
  Providers/
  Services/              # domain services (Ledger, Reconciliation, Notifications)
database/
  migrations/            # schema migrations
  seeders/               # role + permission + sample-data seeders
resources/
  js/
    Layouts/             # AppLayout, AuthLayout
    Pages/               # Inertia pages by module
    Components/          # reusable Vue components
  css/                   # Tailwind entrypoint
routes/
  web.php
  api.php
  console.php
```

## Modules (Phase 1 MVP)

- **Auth + RBAC + audit log** — every action is recorded with actor + before/after.
- **Inventory** — projects → blocks → units, unit categories, unit statuses, plan templates.
- **Clients & bookings** — KYC fields, nominees, co-buyers, plan instantiation onto schedule.
- **Payments & allocations** — single payment table with channel enum, allocation to schedules, reverse-by-counter-entry.
- **Reconciliation** — bank statement import staging, suggested matches, confirm queue.
- **Statements** — branded PDF, on-demand + monthly run.
- **Notifications** — email + SMS at MVP; reminder schedules T-15/-7/-3/-1 + dunning D+1/+7/+15/+30.
- **Reporting** — finance dashboard, collections view, aging.

Phase 2 adds the client self-service portal and GenieCentral bidirectional sync. Phase 3 adds online payment gateway, Urdu UI toggle, and external security audit.

## Conventions

- **Branching:** `main` is protected; feature branches `feat/<scope>` or `fix/<scope>`; PRs require a review.
- **Style:** `composer lint` (Pint) + `npm run lint` (ESLint) in CI. Don't merge red.
- **Tests:** Pest. Aim 70% coverage on ledger / payments / reconciliation modules.
- **Migrations:** never edit a migration after it's been merged; add a new one.
- **Money:** all monetary values stored in **minor units** (paisa) as `bigint`. UI formats to major. Never use floats for money.
- **Time:** all timestamps stored as UTC; rendered in `Asia/Karachi`.

## Deployment

See `docs/DEPLOYMENT.md` (to be written before Phase 1 go-live). Target: Linux VPS (Ubuntu 24.04), Nginx + PHP-FPM, Postgres, Redis, Horizon as a systemd service, off-host backups via Spatie Backup + S3-compatible bucket.

## Security baselines

- HTTPS + HSTS in production; never run with `APP_DEBUG=true` in production
- 2FA mandatory for Super Admin + Finance Admin
- Sensitive fields encrypted at rest using Laravel `encrypted` cast (CNIC, passport, bank ref)
- File uploads scanned (ClamAV) and stored with randomised paths
- Rate limiting on auth and OTP endpoints
- Audit log is append-only; financial entries are reversed by counter-entry, never deleted

See `docs/SECURITY.md` for full posture.

## License

Proprietary — © Zen Retreats. All rights reserved.
