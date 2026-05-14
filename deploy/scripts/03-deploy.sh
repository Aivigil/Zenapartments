#!/usr/bin/env bash
# 03-deploy.sh
# App deploy script. Run as the `deploy` user inside the container, in $APP_DIR.

set -euo pipefail

APP_DIR=/var/www/zenretreats-portal
cd "${APP_DIR}"

if [[ "$(whoami)" != "deploy" ]]; then
  echo "ERROR: run this as the 'deploy' user (sudo -iu deploy)." >&2
  exit 1
fi

echo "=== Step 1: Git pull (skip if cloning fresh) ==="
if [[ -d .git ]]; then
  git fetch --all --tags --prune
  CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
  git reset --hard "origin/${CURRENT_BRANCH}"
fi

echo "=== Step 2: .env check ==="
if [[ ! -f .env ]]; then
  cp .env.example .env
  echo "*** Created .env from .env.example. EDIT IT NOW:"
  echo "    nano .env"
  echo "    Set: APP_ENV, APP_DEBUG=false, APP_URL=https://portal.zenretreatspk.com,"
  echo "         DB_PASSWORD (from /root/secrets.txt), MAIL_*, TRUSTED_PROXIES=*"
  echo "    Then re-run this script."
  exit 1
fi

echo "=== Step 3: Composer install (no dev) ==="
composer install --no-dev --no-interaction --optimize-autoloader

echo "=== Step 4: App key (if not set) ==="
if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

echo "=== Step 5: NPM install + build ==="
# Use 'npm install' on first run when no lockfile exists; falls through to
# lockfile-respecting behaviour once package-lock.json is committed.
if [[ -f package-lock.json ]]; then
  npm ci
else
  npm install
fi
npm run build

echo "=== Step 6: Ziggy routes for the frontend ==="
php artisan ziggy:generate resources/js/ziggy.js || true
# Re-run build if ziggy changed
npm run build

echo "=== Step 7: Storage symlink ==="
php artisan storage:link || true

echo "=== Step 8: Migrate (and seed on first deploy only) ==="
# On first deploy, the migrate:status call will show "Migration table not found" — handle gracefully.
if ! php artisan migrate:status &>/dev/null; then
  echo "First deploy: running migrate --seed (this creates seed data including a default admin login)."
  php artisan migrate --force --seed
else
  php artisan migrate --force
fi

echo "=== Step 9: Caches ==="
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "=== Step 10: Restart Horizon + ensure scheduler timer is active ==="
sudo systemctl daemon-reload
sudo systemctl enable laravel-horizon.service
sudo systemctl restart laravel-horizon.service || true
sudo systemctl enable laravel-scheduler.timer
sudo systemctl restart laravel-scheduler.timer || true

echo "=== Step 11: Verify ==="
php artisan about | head -30 || true

echo ""
echo "========================================================"
echo "Deploy complete."
echo "Local check:  curl -I http://127.0.0.1/"
echo "Public URL:   https://portal.zenretreatspk.com"
echo "========================================================"
