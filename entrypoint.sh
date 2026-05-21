#!/bin/bash
set -e

echo "Creating required directories..."
mkdir -p /app/var/cache /app/var/log
chown -R www-data:www-data /app/var
chmod -R 775 /app/var

echo "Running Symfony cache clear & warmup..."
php /app/bin/console cache:clear --env=prod --no-debug
php /app/bin/console cache:warmup --env=prod --no-debug

echo "Fixing permissions after cache warmup..."
chown -R www-data:www-data /app/var
chmod -R 775 /app/var

echo "Running database migrations..."
php /app/bin/console doctrine:migrations:migrate --no-interaction --env=prod

echo "Starting PHP-FPM..."
php-fpm -F &
PHP_PID=$!

echo "Waiting for PHP-FPM to start..."
sleep 2

echo "Starting Nginx..."
nginx -g "daemon off;"

wait $PHP_PID