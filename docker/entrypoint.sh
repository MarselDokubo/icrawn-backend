#!/usr/bin/env sh
set -e

cd /var/www/html

# Cache/clear for prod
php artisan config:clear || true
php artisan route:clear  || true
php artisan view:clear   || true
php artisan config:cache || true
php artisan route:cache  || true
php artisan view:cache   || true

# Storage symlink (ok if it already exists)
php artisan storage:link || true

# Optional: run migrations only if DB envs are present
if [ -n "$DB_HOST" ] && [ -n "$DB_DATABASE" ] && [ -n "$DB_USERNAME" ]; then
  php artisan migrate --force || true
fi

# Bind Nginx to Render’s port (s6 reads NGINX_PORT)
export NGINX_PORT="${PORT:-8080}"

# Start the base image’s supervisor (PHP-FPM + Nginx via s6-overlay)
exec /init
