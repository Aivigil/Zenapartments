# Deployment kit — `deploy/`

Everything required to stand up `portal.zenretreatspk.com` on the existing `aivigil-central-01` Proxmox host as an LXC container.

Start with **`RUNBOOK.md`** — it walks the full deployment end to end.

## Files

| File | Purpose | Where to run |
|---|---|---|
| `RUNBOOK.md` | Top-to-bottom deployment guide | Read first |
| `scripts/01-create-lxc.sh` | Creates LXC container 210 (`zenretreats-portal`) | Proxmox host, as root |
| `scripts/02-provision.sh` | Installs PHP 8.3, Nginx, Postgres 16, Redis 7, Node 20; sets up deploy user; configures systemd | Inside the container, as root, ONCE |
| `scripts/03-deploy.sh` | App deploy — composer, npm, migrate, cache, restart Horizon | Inside the container, as `deploy` user, every release |
| `nginx/portal.conf` | Site config; symlinked into `sites-enabled` by the provisioner | Inside the container |
| `systemd/laravel-horizon.service` | Queue worker supervisor | Inside the container |
| `systemd/laravel-scheduler.service` + `.timer` | Per-minute scheduler trigger | Inside the container |
| `cloudflared/portal-zenretreats.yml` | Ingress entry for the existing `aivigil-central` Cloudflare Tunnel | Proxmox host |

## Conventions

- Container VMID: **210**
- Hostname: **zenretreats-portal**
- Internal IP: **10.10.0.30** on `vmbr1`
- App path: `/var/www/zenretreats-portal`
- Run user: `deploy`
- Database: `zen_retreats_portal` owned by Postgres role `zenretreats`
- Public hostname: **portal.zenretreatspk.com** via Cloudflare Tunnel
