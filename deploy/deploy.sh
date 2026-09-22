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

php artisan migrate --force
php artisan storage:link 2>/dev/null || true
php artisan optimize

php artisan queue:restart
