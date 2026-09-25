#!/usr/bin/env bash
set -e

# Ensure SQLite file exists if using sqlite
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    mkdir -p database
    touch database/database.sqlite
fi

# Ensure storage link exists
php artisan storage:link || true

# Run database migrations
php artisan migrate --force

# Optionally seed if SEED_DATABASE=true
if [ "${SEED_DATABASE:-false}" = "true" ]; then
    php artisan db:seed --force || true
fi

# Optimize views and routes for production
php artisan optimize || true

# Start web server on Railway assigned PORT
echo "Starting Laravel server on port ${PORT:-8000}..."
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
