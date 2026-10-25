#!/bin/sh
set -eu
# Configuration is created from runtime secrets. Never migrate or seed on startup.
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private bootstrap/cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
exec docker-php-entrypoint "$@"
