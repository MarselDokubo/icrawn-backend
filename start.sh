#!/usr/bin/env bash
set -euo pipefail

# Bind Nginx to Render's dynamic port
export NGINX_PORT="${PORT:-8080}"

# Generate APP_KEY only if missing (avoids breaking prod sessions)
if [ -z "${APP_KEY:-}" ] || [ "${APP_KEY}" = "" ]; then
  php artisan key:generate --force
fi

# Warm caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migrations safe on boot (idempotent on empty/new DB)
php artisan migrate --force || true

# Optional (no-op if already linked)
php artisan storage:link || true

# Make sure permissions are correct at runtime
chown -R www-data:www-data storage bootstrap/cache || true

# Hand off to the image's init (starts Nginx + PHP-FPM under s6)
exec /init
