FROM serversideup/php:8.4-fpm-nginx-alpine

ENV PHP_OPCACHE_ENABLE=1
WORKDIR /var/www/html

USER root

# Keep your pool user tweaks
RUN echo "" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf && \
    echo "user = www-data" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf && \
    echo "group = www-data" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf

# Needed extensions
RUN install-php-extensions intl

# App files
COPY --chown=www-data:www-data . .

# Permissions
RUN chmod -R 755 storage  \
    && mkdir -p bootstrap/cache \
    && chmod -R 775 bootstrap/cache \
    && mkdir -p /var/lib/nginx/tmp \
    && chown -R www-data:www-data /var/lib/nginx \
    && chmod -R 755 /var/lib/nginx

# Composer deps
RUN composer install \
    --ignore-platform-reqs \
    --no-interaction \
    --no-dev \
    --optimize-autoloader \
    --prefer-dist

# HTMLPurifier cache dir fix
RUN mkdir -p /var/www/html/vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer \
    && chmod -R 775 /var/www/html/vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer \
    && chown -R www-data:www-data /var/www/html/vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer

# Start script
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Informational; Render uses $PORT
EXPOSE 8080

# Let Render healthcheck externally (no fixed-port HEALTHCHECK here)

# Bind Nginx to $PORT and launch services
CMD ["bash", "/usr/local/bin/start.sh"]
