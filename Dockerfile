# Base image: PHP-FPM + Nginx + Alpine + helper scripts
FROM serversideup/php:8.3-fpm-nginx-alpine

ENV PHP_OPCACHE_ENABLE=1

# Set working directory where Nginx serves from
WORKDIR /var/www/html

USER root

# Ensure FPM runs as www-data
RUN echo "" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf && \
    echo "user = www-data" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf && \
    echo "group = www-data" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf

# Install needed PHP extensions
RUN install-php-extensions intl

# ---- Leverage build cache for Composer deps ----
COPY composer.json composer.lock ./
RUN composer install \
    --ignore-platform-reqs \
    --no-interaction \
    --no-dev \
    --optimize-autoloader \
    --prefer-dist

# Ensure /etc/nginx exists and copy our config
RUN mkdir -p /etc/nginx
COPY docker/nginx.conf /etc/nginx/nginx.conf

# ---- Copy application source ----
COPY --chown=www-data:www-data . .

# Permissions for Laravel writable dirs
RUN chmod -R 775 storage bootstrap/cache && \
    mkdir -p /var/lib/nginx/tmp && \
    chown -R www-data:www-data /var/lib/nginx && \
    chmod -R 755 /var/lib/nginx

# Workaround for HTMLPurifier cache perms (some stacks need it)
RUN mkdir -p vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer && \
    chmod -R 775 vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer && \
    chown -R www-data:www-data vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer

# Copy entrypoint that runs artisan tasks and starts services
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Don’t hardcode a port; Render passes $PORT.
# EXPOSE is optional; leaving it commented avoids confusion
# EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
