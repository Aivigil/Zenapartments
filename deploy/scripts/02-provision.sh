#!/usr/bin/env bash
# 02-provision.sh
# Provision the zenretreats-portal container.
# Run inside the LXC container as root, ONCE.

set -euo pipefail

APP_USER=deploy
APP_DIR=/var/www/zenretreats-portal
DB_NAME=zen_retreats_portal
DB_USER=zenretreats
DB_PASS="$(openssl rand -base64 32 | tr -d '/+=' | cut -c1-32)"
NODE_MAJOR=20

echo "=== Step 1: Update apt and base packages ==="
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y \
  ca-certificates curl gnupg lsb-release software-properties-common \
  git unzip zip vim less htop ufw fail2ban \
  build-essential pkg-config \
  supervisor unattended-upgrades

timedatectl set-timezone Asia/Karachi

echo "=== Step 2: PHP 8.3 + extensions ==="
add-apt-repository -y ppa:ondrej/php
apt-get update
apt-get install -y \
  php8.3 php8.3-fpm php8.3-cli \
  php8.3-pgsql php8.3-redis php8.3-mbstring php8.3-xml \
  php8.3-bcmath php8.3-curl php8.3-gd php8.3-intl php8.3-zip \
  php8.3-readline php8.3-opcache

# Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
chmod +x /usr/local/bin/composer

echo "=== Step 3: Node ${NODE_MAJOR} + npm ==="
curl -fsSL https://deb.nodesource.com/setup_${NODE_MAJOR}.x | bash -
apt-get install -y nodejs

echo "=== Step 4: Nginx ==="
apt-get install -y nginx
systemctl enable nginx

echo "=== Step 5: PostgreSQL 16 ==="
install -d /usr/share/postgresql-common/pgdg
curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc \
  | gpg --dearmor -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.gpg
echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.gpg] https://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" \
  > /etc/apt/sources.list.d/pgdg.list
apt-get update
apt-get install -y postgresql-16 postgresql-client-16
systemctl enable postgresql

# Create DB + role
sudo -u postgres psql -c "CREATE USER ${DB_USER} WITH ENCRYPTED PASSWORD '${DB_PASS}';"
sudo -u postgres psql -c "CREATE DATABASE ${DB_NAME} OWNER ${DB_USER};"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE ${DB_NAME} TO ${DB_USER};"
sudo -u postgres psql -d "${DB_NAME}" -c "GRANT ALL ON SCHEMA public TO ${DB_USER};"

echo "=== Step 6: Redis 7 ==="
apt-get install -y redis-server
sed -i 's/^# *requirepass .*$/requirepass changeme-set-in-env/' /etc/redis/redis.conf || true
sed -i 's/^supervised .*/supervised systemd/' /etc/redis/redis.conf
systemctl enable redis-server
systemctl restart redis-server

echo "=== Step 7: deploy user + app directory ==="
id "${APP_USER}" &>/dev/null || useradd -m -s /bin/bash "${APP_USER}"
install -d -m 0755 -o "${APP_USER}" -g "${APP_USER}" "${APP_DIR}"
install -d -m 0700 -o "${APP_USER}" -g "${APP_USER}" "/home/${APP_USER}/.ssh"

# Add deploy user to www-data so PHP-FPM (running as www-data) can read uploads
usermod -aG www-data "${APP_USER}"

echo "=== Step 8: PHP-FPM tuning ==="
# Limit upload size + tune opcache
cat > /etc/php/8.3/fpm/conf.d/99-portal.ini <<'EOF'
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
max_execution_time = 60
expose_php = Off
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0
opcache.revalidate_freq = 0
EOF
systemctl enable php8.3-fpm
systemctl restart php8.3-fpm

echo "=== Step 9: Nginx vhost ==="
# Note: 03-deploy.sh expects the app at $APP_DIR; the vhost file ships in the repo.
# Until the repo is cloned, deploy a temp placeholder.
cat > /etc/nginx/sites-available/portal <<'EOF'
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root /var/www/zenretreats-portal/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    charset utf-8;
    client_max_body_size 25M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 60s;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/portal /etc/nginx/sites-enabled/portal
nginx -t
systemctl reload nginx

echo "=== Step 10: Hardening ==="
# UFW (allow only 22 internal — container is on private bridge anyway)
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw allow 80/tcp
ufw --force enable

# fail2ban defaults are fine for SSH
systemctl enable fail2ban

# unattended-upgrades
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
EOF

# SSH hardening — keep on 22 since container is internal
sed -i 's/^#*PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config
sed -i 's/^#*PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
systemctl restart ssh || systemctl restart sshd

echo "=== Step 11: Systemd units for Horizon + scheduler ==="
cat > /etc/systemd/system/laravel-horizon.service <<'EOF'
[Unit]
Description=Laravel Horizon (Zen Retreats Portal)
After=network.target redis-server.service postgresql.service

[Service]
Type=simple
User=deploy
Group=deploy
WorkingDirectory=/var/www/zenretreats-portal
ExecStart=/usr/bin/php /var/www/zenretreats-portal/artisan horizon
Restart=on-failure
RestartSec=5
KillMode=process
KillSignal=SIGTERM
TimeoutStopSec=60

[Install]
WantedBy=multi-user.target
EOF

cat > /etc/systemd/system/laravel-scheduler.service <<'EOF'
[Unit]
Description=Laravel Scheduler (Zen Retreats Portal)
After=network.target

[Service]
Type=oneshot
User=deploy
Group=deploy
WorkingDirectory=/var/www/zenretreats-portal
ExecStart=/usr/bin/php /var/www/zenretreats-portal/artisan schedule:run
EOF

cat > /etc/systemd/system/laravel-scheduler.timer <<'EOF'
[Unit]
Description=Run Laravel scheduler every minute

[Timer]
OnBootSec=1min
OnUnitActiveSec=1min
AccuracySec=1s
Unit=laravel-scheduler.service

[Install]
WantedBy=timers.target
EOF

systemctl daemon-reload
systemctl enable laravel-scheduler.timer
# Don't start Horizon until the app is deployed and .env is configured

echo "=== Step 12: Save secrets ==="
cat > /root/secrets.txt <<EOF
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
REDIS_HOST=127.0.0.1
EOF
chmod 600 /root/secrets.txt

echo ""
echo "========================================================"
echo "Provisioning complete."
echo ""
echo "Postgres credentials saved to /root/secrets.txt — paste DB_PASS into your .env."
echo "Read once, then 'shred -u /root/secrets.txt'."
echo ""
echo "Generated database password:"
echo "    ${DB_PASS}"
echo ""
echo "Next steps (as the deploy user):"
echo "  pct enter 210                # (from Proxmox host)"
echo "  sudo -iu deploy"
echo "  cd /var/www/zenretreats-portal"
echo "  git clone https://<PAT>@github.com/Aivigil/Zenapartments.git ."
echo "  bash deploy/scripts/03-deploy.sh"
echo "========================================================"
