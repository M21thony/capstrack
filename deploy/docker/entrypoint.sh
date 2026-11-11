#!/bin/sh
set -e
cd /var/www/capstrack
chown -R www-data:www-data storage bootstrap/cache
php artisan migrate --force
# With the storage container: wait for it, make its two buckets, and move over
# any files an earlier run kept on this disk. On a fresh install it copies nothing.
if [ "$FILES_ON_S3" = "true" ]; then
    mkdir -p storage/app/private storage/app/public
    tries=0
    until php artisan files:copy-to-s3; do
        tries=$((tries + 1))
        [ "$tries" -lt 30 ] || { echo "The storage container did not answer." >&2; exit 1; }
        sleep 2
    done
fi
php artisan storage:link 2>/dev/null || true
php artisan optimize
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
