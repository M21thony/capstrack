#!/usr/bin/env bash
# Pull the latest code and bring the site up to date. Run on the server:
#   cd /var/www/capstrack && bash deploy/deploy.sh
set -euo pipefail
# New files stay writable by the web server's group.
umask 002
cd "$(dirname "$0")/.."

php artisan down --retry=30 || true
trap 'php artisan up' EXIT

git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

# The site, the queue worker and the every-minute scheduler all run as
# www-data. While `optimize` rebuilds the config cache they read .env itself,
# and one that www-data cannot read makes Laravel fall back to its defaults -
# SQLite, which is not here - so a scheduler run in that moment failed with
# "database.sqlite does not exist". Owner can write, the group can read.
if chgrp www-data .env 2>/dev/null; then
    chmod 640 .env
else
    echo "warning: .env is not readable by www-data - run: sudo chown $USER:www-data .env && sudo chmod 640 .env" >&2
fi

php artisan migrate --force
php artisan storage:link 2>/dev/null || true
php artisan optimize

php artisan queue:restart
