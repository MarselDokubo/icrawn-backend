# Base image: PHP-FPM + Nginx + Alpine + helper scripts (s6-overlay)
FROM serversideup/php:8.3-fpm-nginx-alpine

ENV PHP_OPCACHE_ENABLE=1

# Laravel app lives here (and nginx root will point to /public under this)
WORKDIR /var/www/html

USER root

# Ensure PHP-FPM runs as www-data
RUN printf "\nuser = www-data\ngroup = www-data\n" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf

# PHP extensions needed by the app
RUN install-php-extensions intl

# ---------- Composer deps (use cache) ----------
COPY composer.json composer.lock ./
RUN composer install \
    --ignore-platform-reqs \
    --no-interaction \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader

# ---------- Nginx ----------
RUN mkdir -p /etc/nginx
COPY docker/nginx.conf.template /etc/nginx/nginx.conf.template

# ---------- App code ----------
# Copy code with proper ownership
COPY --chown=www-data:www-data . .

# Ensure writable dirs (Laravel)
RUN mkdir -p storage storage/logs storage/app/public bootstrap/cache /var/lib/nginx/tmp && \
    chown -R www-data:www-data storage bootstrap/cache /var/lib/nginx && \
    chmod -R 775 storage bootstrap/cache && \
    chmod -R 755 /var/lib/nginx

# HTMLPurifier cache workaround (some stacks need it)
RUN mkdir -p vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer && \
    chown -R www-data:www-data vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer && \
    chmod -R 775 vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer

# Autoload refresh after full source copy
RUN composer dump-autoload -o

# ---------- Entrypoint ----------
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Render passes $PORT at runtime; we don’t hardcode EXPOSE or CMD here.
ENTRYPOINT ["/entrypoint.sh"]
