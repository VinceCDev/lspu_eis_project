# syntax=docker/dockerfile:1
#
# Production image for the LSPU EIS Laravel app (PHP 8.2 / Laravel 12).
# Self-contained: the application code and Composer dependencies are baked
# into the image, so it does not need the repo checked out on the host.
# No Node build step: all frontend assets are vendored/pre-compiled and
# committed under public/assets.

FROM php:8.2-fpm-bookworm

# --- OS packages needed at runtime + for `composer install` ---------------
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        default-mysql-client \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# --- PHP extensions (mlocati installer handles all the ./configure flags) -
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
        pdo_mysql \
        mbstring \
        bcmath \
        gd \
        zip \
        intl \
        exif \
        pcntl \
        opcache

# --- Composer binary -----------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- PHP config --------------------------------------------------------
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf

WORKDIR /var/www/html

# --- Composer dependencies (cached layer) -------------------------------
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction \
        --no-scripts --prefer-dist

# --- Application code --------------------------------------------------
COPY . .

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts \
    && php artisan package:discover --ansi || true
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/public \
        public/uploads \
    && chown -R www-data:www-data storage bootstrap/cache public/uploads \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
