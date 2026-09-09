# syntax=docker/dockerfile:1
#
# Production image for the LSPU EIS Laravel app (PHP 8.2 / Laravel 12).
# No Node build step: all frontend assets are vendored/pre-compiled and
# committed under public/assets. This image only needs PHP + Composer.

FROM php:8.2-fpm-bookworm

# --- OS packages needed at runtime + for `composer install` ---------------
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
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

WORKDIR /var/www/html

# App code is bind-mounted at runtime by docker-compose, so we do NOT copy
# it here. `vendor/` is installed on first boot by the entrypoint.
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
