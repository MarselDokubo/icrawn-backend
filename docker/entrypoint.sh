#!/usr/bin/env sh
set -e

cd /var/www/html

# Generate app key if missing
if [ -z "${APP_KEY}" ] || [ "${APP_KEY}" = "" ]; then
  php artisan key:generate --force || true
fi

# Cache/clear configs & routes for prod
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Storage symlink (idempotent)
php artisan storage:link || true

# Run DB migrations (optional; comment out if you prefer manual)
php artisan migrate --force || true

# Bind Nginx to Render’s port
export NGINX_PORT="${PORT:-8080}"

# Start stack provided by serversideup image
exec /usr/local/bin/start-container
