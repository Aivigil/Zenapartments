# Deployment Runbook — portal.zenretreatspk.com

End-to-end deployment of the Zen Retreats Portal onto `aivigil-central-01` (Hetzner / Proxmox) as an LXC container, fronted by your existing Cloudflare Tunnel.

**Target environment**

| Property | Value |
|---|---|
| Public URL | https://portal.zenretreatspk.com |
| Proxmox host | aivigil-central-01 (`65.109.87.81:2222`) |
| Container VMID | `210` |
| Container hostname | `zenretreats-portal` |
| Container internal IP | `10.10.0.30/24` (on `vmbr1`) |
| OS | Ubuntu 24.04 LTS (LXC) |
| Resources | 4 vCPU · 4 GB RAM · 40 GB disk |
| App path | `/var/www/zenretreats-portal` |
| Run user | `deploy` (no shell login, key auth only) |
| DB | PostgreSQL 16 (local to container) |
| Cache / queue | Redis 7 (local to container) |
| Web | Nginx + PHP-FPM 8.3 |
| Workers | Horizon (systemd) |
| Scheduler | Laravel scheduler (systemd timer) |
| TLS | Cloudflare Tunnel (TLS terminates at Cloudflare edge) |

Total time: **~25–35 minutes**, mostly waiting on package installs and the first `composer install`.

---

## Pre-flight

You'll need:

- SSH to aivigil-central-01: `ssh adminuser@65.109.87.81 -p 2222 -i ~/.ssh/muneeb-aivigil-central`
- A GitHub Personal Access Token (PAT) with `repo` scope, OR a deploy SSH key added to the `Aivigil/Zenapartments` repo
- Cloudflare account access for the tunnel that already runs on this host (`aivigil-central`)
- DNS access for `zenretreatspk.com`

---

## Phase 1 — Create the LXC container (5 min, on the Proxmox host)

SSH to the host:

```bash
ssh adminuser@65.109.87.81 -p 2222 -i ~/.ssh/muneeb-aivigil-central
sudo -i
```

Make sure the latest Ubuntu 24.04 LXC template is available:

```bash
pveam update
pveam available | grep ubuntu-24.04
# If not already downloaded:
pveam download local ubuntu-24.04-standard_24.04-1_amd64.tar.zst
```

Create the container (script in this repo: `deploy/scripts/01-create-lxc.sh`):

```bash
# Copy the script onto the host
scp -P 2222 -i ~/.ssh/muneeb-aivigil-central \
  deploy/scripts/01-create-lxc.sh \
  adminuser@65.109.87.81:/tmp/

# On the host, as root:
sudo bash /tmp/01-create-lxc.sh
```

The script creates VMID 210, mounts the Ubuntu 24.04 template, sets the static IP to 10.10.0.30, enables nesting (needed for Docker if we ever want it), starts the container, and waits for it to be reachable.

When done you'll see `Container 210 ready` and the script prints a one-time root password. Note it but you'll only use it once — we add your SSH key in Phase 2.

---

## Phase 2 — Provision the container (10 min, inside the container)

Enter the container from the host:

```bash
pct enter 210
```

You're now root inside the new container. Run the provisioner:

```bash
# Pull the provisioner from the deploy kit. Two options:

# Option A: paste the script in via stdin (no GitHub access needed)
cat > /root/provision.sh <<'EOF'
<paste contents of deploy/scripts/02-provision.sh here>
EOF

# Option B: once GitHub is reachable from the container, just curl it:
curl -fsSL https://raw.githubusercontent.com/Aivigil/Zenapartments/main/deploy/scripts/02-provision.sh -o /root/provision.sh

chmod +x /root/provision.sh
/root/provision.sh
```

The provisioner:

- Updates apt, sets timezone to Asia/Karachi
- Installs PHP 8.3, Nginx, PostgreSQL 16, Redis 7, Node 20, Composer, Git, supervisor, fail2ban, unattended-upgrades
- Creates a non-root `deploy` user with the app directory pre-owned
- Sets a strong random Postgres password for `zenretreats` user
- Creates the `zen_retreats_portal` database
- Hardens SSH (no root login, no password auth, key only), keeps port 22 (container is on private bridge — not reachable from internet)
- Saves all generated secrets to `/root/secrets.txt` for one-time reading then deletion

Take the printed Postgres password — you'll plug it into `.env` in Phase 3.

Then add your SSH key to the `deploy` user so subsequent deploys don't need root:

```bash
mkdir -p /home/deploy/.ssh
echo "ssh-ed25519 AAAA...your-public-key-here..." > /home/deploy/.ssh/authorized_keys
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys
```

Exit the container:

```bash
exit
```

---

## Phase 3 — Deploy the app (10 min, as deploy user)

From the Proxmox host, become the deploy user inside the container:

```bash
pct exec 210 -- sudo -iu deploy
```

Clone the repo (HTTPS with PAT, or SSH if you set up a deploy key):

```bash
cd /var/www/zenretreats-portal
# HTTPS with PAT:
git clone https://<YOUR-PAT>@github.com/Aivigil/Zenapartments.git .
# Or SSH:
# git clone git@github.com:Aivigil/Zenapartments.git .
```

Run the app-deploy script (also in this repo):

```bash
bash deploy/scripts/03-deploy.sh
```

What it does:

- `cp .env.example .env` (only if .env doesn't exist) and prompts you to fill in DB password
- `composer install --no-dev --optimize-autoloader`
- `php artisan key:generate`
- `npm ci && npm run build`
- `php artisan migrate --force --seed` (one-time on first deploy; comment out on subsequent runs)
- `php artisan storage:link`
- `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- `php artisan ziggy:generate resources/js/ziggy.js`
- Restarts horizon + scheduler systemd units

Before the script runs migrate, edit `/var/www/zenretreats-portal/.env` and set:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://portal.zenretreatspk.com

DB_PASSWORD=<paste the Postgres password from Phase 2>

# Mail — use Mailpit on the host for now or your SMTP provider
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@portal.zenretreatspk.com

# Trust the Cloudflare proxy
TRUSTED_PROXIES=*
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

# Pakistan locale
APP_TIMEZONE=Asia/Karachi
DEFAULT_CURRENCY=PKR
```

Re-run `bash deploy/scripts/03-deploy.sh` after editing .env.

Verify locally inside the container:

```bash
curl -I http://127.0.0.1/
# Expect: HTTP/1.1 302 redirect to /login

curl http://127.0.0.1/up
# Expect: OK
```

---

## Phase 4 — Cloudflare Tunnel ingress (5 min, on the Proxmox host)

You already have a Cloudflare Tunnel called `aivigil-central` terminating on the host. Add an ingress rule for `portal.zenretreatspk.com` → the container.

**If `zenretreatspk.com` DNS is already on Cloudflare under the Aivigil account:**

Edit `/etc/cloudflared/config.yml` on the host (the file template is in this repo at `deploy/cloudflared/portal-zenretreats.yml`). Append the new ingress entry **before** the catch-all 404 entry:

```yaml
  - hostname: portal.zenretreatspk.com
    service: http://10.10.0.30:80
    originRequest:
      noTLSVerify: true
      connectTimeout: 10s
```

Apply:

```bash
sudo cloudflared tunnel route dns aivigil-central portal.zenretreatspk.com
sudo systemctl restart cloudflared
sudo systemctl status cloudflared --no-pager
```

**If `zenretreatspk.com` DNS is NOT on Cloudflare under the same account:**

Add a CNAME at the current DNS provider:

```
portal.zenretreatspk.com  CNAME  <tunnel-id>.cfargotunnel.com
```

(Find `<tunnel-id>` with `cloudflared tunnel list` — the UUID of the `aivigil-central` tunnel.)

Either way, then verify:

```bash
dig +short portal.zenretreatspk.com
curl -I https://portal.zenretreatspk.com/up
# Expect HTTP/2 200 with body "OK"
```

---

## Phase 5 — Smoke test (5 min)

Browser: https://portal.zenretreatspk.com/login

Log in with the seeded admin:

- Email: `admin@zenretreats.local`
- Password: `password`

**Then immediately rotate this password.** Settings → Profile → Change password. Repeat for `finance@zenretreats.local` and `sales@zenretreats.local`. Or delete them and create real accounts.

Verify each module renders:

- Dashboard
- Inventory → Projects (Zen Retreats — Barian seeded)
- Inventory → Units (14 seeded units)
- Inventory → Unit categories (5 seeded)
- /horizon (Horizon dashboard — verify queue workers are up)

Tail the logs (inside the container) to make sure no errors:

```bash
pct exec 210 -- tail -f /var/www/zenretreats-portal/storage/logs/laravel.log
```

---

## Continuous deployments

For ongoing pushes:

```bash
# Locally
git push origin main

# Then on the server (or via a GitHub Action — see deploy/scripts/deploy-on-push.sh):
pct exec 210 -- sudo -iu deploy bash -c "cd /var/www/zenretreats-portal && bash deploy/scripts/03-deploy.sh"
```

We can wire this up properly with GitHub Actions + a deploy webhook in Phase 1.5 if desired.

---

## Rollback

```bash
pct exec 210 -- sudo -iu deploy bash -c "cd /var/www/zenretreats-portal && git reset --hard <previous-commit-sha> && bash deploy/scripts/03-deploy.sh"
```

For a really bad day:

```bash
# Stop the container, restore from snapshot (Proxmox UI: snapshot a known-good state)
sudo pct stop 210
sudo pct rollback 210 <snapshot-name>
sudo pct start 210
```

Take a Proxmox snapshot before each production deploy:

```bash
sudo pct snapshot 210 pre-deploy-$(date +%Y%m%d-%H%M)
```

---

## Backups

The container's `/var/www/zenretreats-portal/storage` and the Postgres dump go off-host nightly. See `deploy/scripts/backup.sh` (to be added in Phase 1.5).

For now, take a manual Postgres dump anytime with:

```bash
pct exec 210 -- sudo -u postgres pg_dump zen_retreats_portal | gzip > /root/zr-portal-$(date +%Y%m%d).sql.gz
```

---

## Files in this kit

```
deploy/
├── RUNBOOK.md                 # this file
├── scripts/
│   ├── 01-create-lxc.sh       # run on Proxmox host
│   ├── 02-provision.sh        # run inside the new container
│   └── 03-deploy.sh           # run as deploy user, each deploy
├── nginx/
│   └── portal.conf            # site config; provisioner symlinks it into sites-enabled
├── systemd/
│   ├── horizon.service        # Laravel Horizon (queue workers)
│   └── laravel-scheduler.service + .timer  # invokes `php artisan schedule:run` each minute
└── cloudflared/
    └── portal-zenretreats.yml # ingress snippet for the existing tunnel config
```
