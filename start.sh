#!/bin/sh
set -eu

# Bind Nginx to Render's dynamic port
export NGINX_PORT="${PORT:-8080}"

# Generate APP_KEY only if missing
if [ -z "${APP_KEY:-}" ]; then
  php artisan key:generate --force
fi

# Warm caches (route:cache can fail if closures)
php artisan config:cache
php artisan route:cache || true
php artisan view:cache

# NO migrations (per your choice)
# php artisan migrate --force || true

# Optional
php artisan storage:link || true

# Permissions (best-effort)
chown -R www-data:www-data storage bootstrap/cache || true

# Hand off to s6 (starts Nginx + PHP-FPM)
exec /init
