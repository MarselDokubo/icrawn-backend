#!/usr/bin/env sh
set -euo pipefail

cd /var/www/html

# ---- tiny log helpers ----
log()  { printf "\033[1;34m[entrypoint]\033[0m %s\n" "$*"; }
warn() { printf "\033[1;33m[entrypoint:warn]\033[0m %s\n" "$*"; }
err()  { printf "\033[1;31m[entrypoint:error]\033[0m %s\n" "$*"; }

# ---- Render sets $PORT at runtime. We'll use it everywhere. ----
: "${PORT:=8080}"                      # safety default if PORT missing
export NGINX_PORT="${PORT}"            # serversideup helper scripts respect this

# ---- Filesystem / permissions (idempotent) ----
# Laravel writable bits
mkdir -p storage storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R 775 storage bootstrap/cache || true

# Ensure log file exists & is writable to avoid Monolog "permission denied"
touch storage/logs/laravel.log
chown www-data:www-data storage/logs/laravel.log || true
chmod 664 storage/logs/laravel.log || true

# ---- Clear any bundled/conflicting nginx vhost configs ----
# Some base images ship with /etc/nginx/conf.d/*.conf listening on 80/8080 – nuke them.
if [ -d /etc/nginx/conf.d ]; then
  rm -f /etc/nginx/conf.d/*.conf || true
fi

# ---- Render our nginx.conf from template, pinning listen to $PORT ----
if [ -f /etc/nginx/nginx.conf.template ]; then
  sed "s/__PORT__/${PORT}/g" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf
else
  warn "/etc/nginx/nginx.conf.template not found; nginx may fail to start."
fi

# ---- App sanity checks ----
if [ -z "${APP_KEY:-}" ]; then
  warn "APP_KEY is not set. Sessions/encryption will break. Set APP_KEY=base64:... in Render env."
fi

# ---- Laravel caches ----
log "Clearing caches…"
php artisan config:clear || true
php artisan route:clear  || true
php artisan view:clear   || true

log "Caching config/routes/views…"
php artisan config:cache || true
php artisan route:cache  || true
php artisan view:cache   || true

# ---- Storage symlink (safe if already exists) ----
log "Ensuring storage symlink…"
php artisan storage:link || true

# ---- Optional: wait for DB & run migrations (only if DB envs exist) ----
if [ -n "${DB_HOST:-}" ] && [ -n "${DB_DATABASE:-}" ] && [ -n "${DB_USERNAME:-}" ]; then
  log "Waiting for database and running migrations…"
  tries=0
  # tiny PDO probe avoids booting the whole framework
  until php -r '
    $driver = getenv("DB_CONNECTION") ?: "pgsql";
    $dsn = $driver.":host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE");
    try { new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD")); echo "ok\n"; }
    catch (Throwable $e) { exit(1); }
  '; do
    tries=$((tries+1))
    if [ "$tries" -ge 10 ]; then
      warn "Database not reachable after ${tries} attempts — skipping migrations."
      break
    fi
    sleep 2
  done

  if [ "$tries" -lt 10 ]; then
    php artisan migrate --force || warn "php artisan migrate failed (continuing startup)"
  fi
else
  warn "DB_* env vars not set; skipping migrations."
fi

# ---- Final: start supervised services (php-fpm + nginx via s6-overlay) ----
log "Starting services on port ${NGINX_PORT}…"
exec /init
