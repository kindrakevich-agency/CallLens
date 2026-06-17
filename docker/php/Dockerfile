# CallLens API image — PHP 8.5 + PHP-FPM (Nginx + PHP-FPM only; no FrankenPHP).
# syntax=docker/dockerfile:1

FROM php:8.5-fpm-bookworm AS base

# System deps for the PHP extensions we need.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libicu-dev libzip-dev libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# Core extensions: pdo_pgsql (Postgres), intl, zip, bcmath.
# (OPcache is compiled into PHP 8.5 core; we enable it below, no ext-install.)
# Installed serially — the parallel -j flag can race the install-modules step.
RUN docker-php-ext-configure intl \
    && docker-php-ext-install pdo_pgsql \
    && docker-php-ext-install intl \
    && docker-php-ext-install zip \
    && docker-php-ext-install bcmath \
    && (docker-php-ext-enable opcache || true)

# Redis extension via PECL.
RUN pecl install redis && docker-php-ext-enable redis

# Composer (copied from the official image).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-calllens.ini

# -----------------------------------------------------------------------------
# Dev stage — adds Xdebug, runs as the app code mounted via compose.
# -----------------------------------------------------------------------------
FROM base AS dev
RUN pecl install xdebug && docker-php-ext-enable xdebug
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini
ENV COMPOSER_ALLOW_SUPERUSER=1
CMD ["php-fpm"]

# -----------------------------------------------------------------------------
# Prod stage — copies code, installs deps, no Xdebug.
# -----------------------------------------------------------------------------
FROM base AS prod
ENV APP_ENV=prod
COPY apps/api/ /app/
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    && composer dump-env prod || true
CMD ["php-fpm"]
