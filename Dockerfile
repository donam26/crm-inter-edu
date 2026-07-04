# syntax=docker/dockerfile:1.7
# Requires BuildKit (default in Docker 23+).
#
# Multi-stage build for the CRM (Laravel 12, server-rendered Blade + Vite/Tailwind).
#   frontend -> compiles CSS/JS assets (public/build) with Vite
#   base     -> php:8.3-fpm-alpine + nginx + supervisor + PHP extensions
#   deps     -> composer install (no-dev) from the lock file
#   app      -> final runtime image (php-fpm + nginx + queue worker + scheduler)

# ---- Frontend asset build (Vite + TailwindCSS 4 + Alpine.js) ----
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---- PHP base ----
FROM php:8.3-fpm-alpine AS base

# Web server + process manager + libs for the PHP extensions we build.
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    icu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    sqlite \
    sqlite-dev \
    linux-headers

# PHP extensions the app needs (mysql primary, sqlite for tests).
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        pdo_sqlite \
        mbstring \
        zip \
        gd \
        intl \
        opcache \
        pcntl \
        bcmath

# Redis PHP extension (build deps stripped afterwards to keep the image small).
# The app defaults to the database driver, but the extension is here so you can
# switch CACHE/SESSION/QUEUE to redis without rebuilding.
RUN apk add --no-cache --virtual .redis-deps autoconf gcc g++ make \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .redis-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ---- Dependencies stage ----
FROM base AS deps

COPY composer.json composer.lock ./

# Install from the lock file. Scripts/autoloader run later after the source is copied.
RUN --mount=type=cache,target=/root/.composer/cache,sharing=locked \
    composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# ---- Application stage ----
FROM base AS app

# Vendored PHP deps
COPY --from=deps /var/www/html/vendor ./vendor

# Application source
COPY . .

# Compiled frontend assets (public/build/manifest.json + assets)
COPY --from=frontend /app/public/build ./public/build

# Optimised autoloader (runs package:discover via post-autoload-dump).
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# Syntax-check the hot path before producing an image.
RUN find app config routes database -name '*.php' -print0 \
    | xargs -0 -n1 -P4 php -l > /dev/null

# Storage / cache directories
RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache \
    && mkdir -p storage/logs storage/app/public bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# PHP production configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini "$PHP_INI_DIR/conf.d/99-custom.ini"
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-custom.conf
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD curl -f http://localhost:8000/up || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
