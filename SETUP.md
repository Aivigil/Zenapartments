# Setup — Local Development

This guide walks you from a fresh clone to a running portal with seeded data and a working login.

Time required: **~10 minutes** the first time.

## Prerequisites

- PHP **8.3** with extensions: `mbstring`, `xml`, `gd`, `pdo_pgsql` (or `pdo_mysql`), `redis` (optional), `intl`, `zip`
- Composer 2.x
- Node.js **20+** and npm
- Docker + Docker Compose (for Postgres / Redis / Mailpit in dev)

> If you don't have Docker, install Postgres 14+ and Redis 7 directly and update `.env` to point at them.

## Steps

### 1. Clone + install

```bash
git clone <repo-url> zen-retreats-portal
cd zen-retreats-portal
composer install
npm install
```

### 2. Environment file + app key

```bash
cp .env.example .env
php artisan key:generate
```

Open `.env` and confirm the values. The defaults assume Docker for Postgres/Redis on localhost.

### 3. Start infra services

```bash
docker compose up -d postgres redis mailpit
```

- Postgres → `localhost:5432`
- Redis → `localhost:6379`
- Mailpit UI → http://localhost:8025 (catches all dev email)

Wait ~5 seconds, then verify:

```bash
docker compose ps      # all should show "healthy"
```

### 4. Database + seed

```bash
php artisan migrate --seed
```

This:

- Runs every migration (creates ~30 tables)
- Seeds RBAC roles + permissions
- Creates three login users (admin, finance, sales)
- Creates the Zen Retreats Barian project + 14 sample units across two blocks
- Creates three reusable plan templates

### 5. Run dev servers

Open two terminals:

```bash
# Terminal 1 — PHP
php artisan serve
# → http://localhost:8000

# Terminal 2 — Vite (HMR)
npm run dev
```

Optional third terminal for queue workers (only needed once you start sending notifications):

```bash
php artisan horizon     # Horizon UI at /horizon
```

### 6. Log in

http://localhost:8000/login

| Email | Password | Role |
|---|---|---|
| `admin@zenretreats.local` | `password` | super_admin |
| `finance@zenretreats.local` | `password` | finance_admin |
| `sales@zenretreats.local` | `password` | sales |

**Change every seeded password before any non-local deployment.**

### 7. Try the inventory module

- Dashboard → see the seeded totals
- Inventory → Projects → "Zen Retreats — Barian" → see the seeded blocks
- Inventory → Units → filter by project, see all 14 seeded units
- Create a new unit, edit, archive

## Useful commands

```bash
# Pint (code style)
composer lint
composer lint:check

# Tests
composer test

# Refresh database (drops everything, re-seeds)
php artisan migrate:fresh --seed

# Regenerate Ziggy route definitions for the frontend
php artisan ziggy:generate resources/js/ziggy.js

# Clear caches
php artisan optimize:clear
```

## Troubleshooting

- **`SQLSTATE[08006] could not connect to server`** — Postgres container not up yet. Wait a few seconds and retry, or check `docker compose logs postgres`.
- **`MissingAppKeyException`** — `.env` is missing the APP_KEY. Run `php artisan key:generate`.
- **`Class "Spatie\Permission\..." not found`** — run `composer install` and `php artisan package:discover`.
- **Vite "import.meta.glob" empty** — make sure `npm run dev` is running and you've reloaded the page.
- **`ziggy.js` 404** — run `php artisan ziggy:generate resources/js/ziggy.js`. Until you do, the stub in `resources/js/ziggy.js` keeps the build green.

## Folder layout (top-level)

```
app/            PHP application code
bootstrap/      Laravel boot files
config/         Configuration
database/       Migrations + seeders + factories
public/         Public webroot
resources/      Frontend (Vue/Inertia) + Blade root + CSS
routes/         web.php, api.php, console.php
storage/        Logs, sessions, cached views, uploads
tests/          Pest tests
```

## Going to staging / production

See `docs/DEPLOYMENT.md` (to be written before Phase 1 staging cut). Do **NOT** simply rsync to a cPanel account — the old portal was compromised at that level. The new portal runs on a separate, hardened VPS with proper user separation, fail2ban, automatic security updates, off-host backups, and TLS via Let's Encrypt.
