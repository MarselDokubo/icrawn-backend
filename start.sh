#!/usr/bin/env bash
set -euo pipefail

# Bind Nginx to Render's dynamic port
export NGINX_PORT="${PORT:-8080}"

# Use a fixed APP_KEY in Render; this is a fallback if it's missing
if [ -z "${APP_KEY:-}" ] || [ "${APP_KEY}" = "" ]; then
  php artisan key:generate --force
fi

# Warm caches
php artisan config:cache
# If you use route closures in prod, make this tolerant or skip it
php artisan route:cache || true
php artisan view:cache

# NO MIGRATIONS HERE

# Optional: symlink storage (no-op if already linked)
php artisan storage:link || true

# Permissions (best-effort)
chown -R www-data:www-data storage bootstrap/cache || true

# Start nginx + php-fpm (s6)
exec /init
