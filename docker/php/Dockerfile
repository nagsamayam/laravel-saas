FROM php:8.4-fpm-alpine

# Install PHP extension installer
COPY --from=mlocati/php-extension-installer \
    /usr/bin/install-php-extensions \
    /usr/local/bin/

# Install required PHP extensions and their runtime dependencies
RUN install-php-extensions \
    pdo_pgsql \
    zip \
    gd \
    pcntl \
    opcache \
    redis \
    amqp

WORKDIR /var/www/html

# Use production PHP configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Configure OPcache
COPY docker/php/prod-opcache.ini \
    "$PHP_INI_DIR/conf.d/opcache.ini"

# Create a non-root application user
RUN adduser -D \
    -u 1000 \
    -h /home/laravel \
    -G www-data \
    laravel

# Copy application files with correct ownership
COPY --chown=laravel:laravel . /var/www/html

# Ensure Laravel writable directories have appropriate permissions
RUN mkdir -p /var/www/html/storage/framework/cache/data \
             /var/www/html/storage/framework/sessions \
             /var/www/html/storage/framework/views \
             /var/www/html/storage/logs \
             /var/www/html/bootstrap/cache \
    && chown -R laravel:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R ug+rwx /var/www/html/storage /var/www/html/bootstrap/cache

# Run as non-root user
USER laravel

EXPOSE 9000

CMD ["php-fpm"]
