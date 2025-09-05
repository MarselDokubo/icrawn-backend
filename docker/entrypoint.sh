#!/usr/bin/env sh
set -euo pipefail

cd /var/www/html

log()  { printf "\033[1;34m[entrypoint]\033[0m %s\n" "$*"; }
warn() { printf "\033[1;33m[entrypoint:warn]\033[0m %s\n" "$*"; }

# ----- Render runtime port -----
: "${PORT:=8080}"     # default if Render hasn't injected yet
unset NGINX_PORT || true

# ----- Nginx config -----
# Clean potential default vhosts
[ -d /etc/nginx/conf.d ]      && rm -f /etc/nginx/conf.d/*.conf      || true
[ -d /etc/nginx/http.d ]      && rm -f /etc/nginx/http.d/*.conf      || true
[ -d /etc/nginx/sites-enabled ] && rm -f /etc/nginx/sites-enabled/*  || true

if [ -f /etc/nginx/nginx.conf.template ]; then
  sed "s/__PORT__/${PORT}/g" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf
  grep -n "listen" /etc/nginx/nginx.conf || true
else
  warn "/etc/nginx/nginx.conf.template not found; nginx may fail to start."
fi

# ----- Laravel file system prep -----
mkdir -p \
  storage storage/logs storage/framework/{cache,sessions,views} \
  storage/app/public storage/app/public/event-images \
  bootstrap/cache

# Ensure log exists & is writable
touch storage/logs/laravel.log

# Ownership & perms (www-data is the php-fpm/nginx user)
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
chmod 664 storage/logs/laravel.log || true

# Warn if missing APP_KEY (don’t block boot)
[ -z "${APP_KEY:-}" ] && warn "APP_KEY is not set. Set APP_KEY=base64:... in Render env."

# ----- Laravel caches -----
log "Clearing caches…"
php artisan config:clear || true
php artisan route:clear  || true
php artisan view:clear   || true

log "Caching config/routes/views…"
php artisan config:cache || true
php artisan route:cache  || true   # ok to fail if closures
php artisan view:cache   || true

# Storage symlink (safe if it already exists)
log "Ensuring storage symlink…"
php artisan storage:link || true

# ----- DB wait & migrations (non-blocking) -----
if [ -n "${DB_HOST:-}" ] && [ -n "${DB_DATABASE:-}" ] && [ -n "${DB_USERNAME:-}" ]; then
  log "Waiting for database and running migrations…"
  : "${DB_CONNECTION:=pgsql}"
  : "${DB_PORT:=$( [ "$DB_CONNECTION" = "mysql" ] && echo 3306 || echo 5432 )}"
  tries=0
  until php -r '
    $driver = getenv("DB_CONNECTION") ?: "pgsql";
    $dsn = $driver.":host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE");
    try { new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD")); echo "ok\n"; }
    catch (Throwable $e) { exit(1); }
  '; do
    tries=$((tries+1))
    [ "$tries" -ge 10 ] && { warn "DB not reachable, skipping migrations."; break; }
    sleep 2
  done
  [ "$tries" -lt 10 ] && php artisan migrate --force --no-interaction || true
else
  warn "DB_* env vars not set; skipping migrations."
fi

# Final ownership pass (handles files created by artisan)
chown -R www-data:www-data storage bootstrap/cache

# ----- Start services (php-fpm + nginx via s6-overlay) -----
log "Starting services on port ${PORT}…"
exec /init
