#!/usr/bin/env bash
set -e

# Support dynamic port binding (Render/Railway sets $PORT)
if [ -n "$PORT" ]; then
    sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:$PORT>/g" /etc/apache2/sites-available/000-default.conf
fi

# Ensure SQLite file exists if using sqlite
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    mkdir -p database
    touch database/database.sqlite
    chown -R www-data:www-data database
fi

# Ensure storage link
php artisan storage:link || true

# Run migrations
php artisan migrate --force

# Seed database if requested
if [ "${SEED_DATABASE:-false}" = "true" ]; then
    php artisan db:seed --force || true
fi

# Optimize views and configuration for production
php artisan optimize || true

# Fix permissions
chown -R www-data:www-data storage bootstrap/cache database

# Start Apache in foreground
exec apache2-foreground
