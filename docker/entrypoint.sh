#!/bin/sh
set -e

# Supervisor log directory
mkdir -p /var/log/supervisor

# Ensure storage directories exist with correct permissions (storage/ is a mounted volume).
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache
mkdir -p storage/logs storage/app/public bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Laravel log file
touch storage/logs/laravel.log
chown www-data:www-data storage/logs/laravel.log
chmod 644 storage/logs/laravel.log

# public/storage symlink
if [ -d "public/storage" ] && [ ! -L "public/storage" ]; then
    rm -rf public/storage
fi
php artisan storage:link --force

# Production optimizations
if [ "$APP_ENV" = "production" ] || [ "$APP_DEBUG" = "false" ]; then
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    php artisan cache:clear 2>/dev/null || true

    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Migrations only run when explicitly enabled (safer for production).
if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force --no-interaction
    echo "==> Migrations completed"
else
    echo "==> Migrations skipped (set RUN_MIGRATIONS=true to run on boot)"
fi

echo "==> CRM ready!"
exec "$@"
