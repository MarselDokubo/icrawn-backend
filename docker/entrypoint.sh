#!/usr/bin/env sh
set -euo pipefail

cd /var/www/html

log() { printf "\033[1;34m[entrypoint]\033[0m %s\n" "$*"; }
warn() { printf "\033[1;33m[entrypoint:warn]\033[0m %s\n" "$*"; }
err() { printf "\033[1;31m[entrypoint:error]\033[0m %s\n" "$*"; }

# Helpful defaults
export NGINX_PORT="${PORT:-8080}"
envsubst '$NGINX_PORT' \
  < /etc/nginx/nginx.conf.template \
  > /etc/nginx/nginx.conf

# Sanity: permissions (usually already set in Dockerfile, but safe here)
# If this ever fails (read-only FS), it won't crash thanks to `|| true`.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Warn if APP_KEY is missing — Laravel will boot but encryption/sessions break.
if [ -z "${APP_KEY:-}" ]; then
  warn "APP_KEY is not set. Set APP_KEY=base64:... in Render Environment before going live."
fi

# Clear + cache
log "Clearing caches…"
php artisan config:clear || true
php artisan route:clear  || true
php artisan view:clear   || true

log "Caching config/routes/views…"
php artisan config:cache || true
php artisan route:cache  || true
php artisan view:cache   || true

# Storage symlink (idempotent)
log "Ensuring storage symlink…"
php artisan storage:link || true

# Optional: run migrations with a short retry (helps with cold DBs)
if [ -n "${DB_HOST:-}" ] && [ -n "${DB_DATABASE:-}" ] && [ -n "${DB_USERNAME:-}" ]; then
  log "Waiting for database and running migrations…"
  tries=0
  until php -r 'try{new PDO(getenv("DB_CONNECTION").":host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); echo "ok\n";}catch(Exception $e){exit(1);}'; do
    tries=$((tries+1))
    if [ "$tries" -ge 10 ]; then
      warn "Database not reachable after ${tries} attempts — continuing without running migrations."
      break
    fi
    sleep 2
  done
  if [ "$tries" -lt 10 ]; then
    php artisan migrate --force || warn "php artisan migrate failed (continuing)"
  fi
else
  warn "DB env vars not set; skipping migrations."
fi
# Ensure PORT exists (Render sets it at runtime)
: "${PORT:=8080}"

# Render Nginx config from template by substituting __PORT__
sed "s/__PORT__/${PORT}/" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Hand off to s6-overlay supervisor (starts php-fpm + nginx)
log "Starting services on port ${NGINX_PORT}…"
head -n 40 /etc/nginx/nginx.conf | sed -n '1,60p'
exec /init
