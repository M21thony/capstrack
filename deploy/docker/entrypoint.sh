#!/bin/sh
set -e
cd /var/www/capstrack
chown -R www-data:www-data storage bootstrap/cache
php artisan migrate --force
php artisan storage:link 2>/dev/null || true
php artisan optimize
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
