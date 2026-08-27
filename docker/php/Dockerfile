FROM php:8.4-fpm-alpine

WORKDIR /var/www/html

# Install base system utilities
RUN apk add --no-cache automake libtool g++ make shadow git unzip

# Cleanly install production-grade extensions
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql zip gd pcntl opcache redis amqp

# FIX: Crucial addition of Composer binary so it is available in $PATH
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Set production php.ini settings
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/opcache.ini $PHP_INI_DIR/conf.d/opcache.ini

# Security & Ownership permissions
RUN useradd -G www-data,root -u 1000 -d /home/laravel laravel
RUN chown -R laravel:laravel /var/www/html

USER laravel

EXPOSE 9000
CMD ["php-fpm"]
