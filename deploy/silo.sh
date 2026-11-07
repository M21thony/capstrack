#!/usr/bin/env bash
# Move the uploaded files from storage/app onto Silo, on this same server.
# Run deploy/deploy.sh first, then these one at a time from /var/www/capstrack:
#
#   bash deploy/silo.sh install   Silo and its keys. The site still uses the disk.
#   bash deploy/silo.sh copy      Copy every file and compare. Safe to repeat.
#   bash deploy/silo.sh switch    The site starts using Silo.
#   bash deploy/silo.sh back      Undo: back to the disk, new uploads included.
#
# Nothing here deletes storage/app, so `back` stays possible.
set -euo pipefail
umask 002
cd "$(dirname "$0")/.."

RELEASE=RELEASE.2026-10-26T00-00-00Z
DEB=silo_20261026000000.0.0-1PGSTY_amd64.deb
SHA256=317d105e5ff1b757094cc11cc00063ec95c7dc824455f4150a7174f3242bb9aa
ENDPOINT=http://127.0.0.1:9000

env_get() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- || true; }

# sed -i makes a new file, so put back the group the site reads it through.
env_set() {
    if grep -qE "^$1=" .env; then
        sed -i "s|^$1=.*|$1=$2|" .env
    else
        echo "$1=$2" >> .env
    fi
    sudo chgrp www-data .env
    chmod 640 .env
}

# As www-data, so it can read every upload. The cached config says FILES_ON_S3
# is off; pointing past it lets this one command see the buckets while the site
# carries on using the disk.
on_silo() {
    sudo -u www-data env APP_CONFIG_CACHE=/tmp/capstrack-silo-config.php FILES_ON_S3=true \
        php artisan files:copy-to-s3 "$@"
}

reload_site() {
    php artisan optimize
    sudo systemctl reload php8.4-fpm
    php artisan queue:restart
}

case "${1:-}" in
install)
    curl -fsSL -o "/tmp/$DEB" "https://github.com/pgsty/silo/releases/download/$RELEASE/$DEB"
    echo "$SHA256  /tmp/$DEB" | sha256sum -c -
    sudo apt-get install -y "/tmp/$DEB"
    rm -f "/tmp/$DEB"
    sudo install -d -o silo -g silo -m 750 /var/lib/silo

    # Made once: a second run must not lock the site out of its own files.
    key=$(env_get AWS_ACCESS_KEY_ID)
    secret=$(env_get AWS_SECRET_ACCESS_KEY)
    [ -n "$key" ] || key=$(openssl rand -hex 10)
    [ -n "$secret" ] || secret=$(openssl rand -hex 20)

    # Reachable from this server only.
    sudo tee /etc/default/silo > /dev/null <<EOF
MINIO_ROOT_USER=$key
MINIO_ROOT_PASSWORD=$secret
MINIO_VOLUMES=/var/lib/silo
MINIO_OPTS="--address 127.0.0.1:9000 --console-address 127.0.0.1:9001"
GOMEMLIMIT=512MiB
EOF
    sudo chown root:silo /etc/default/silo
    sudo chmod 640 /etc/default/silo

    # The package shields Silo from the out-of-memory killer. With 4 GB, no swap
    # and MySQL on the same server, it would be MySQL that dies instead.
    sudo mkdir -p /etc/systemd/system/silo.service.d
    sudo tee /etc/systemd/system/silo.service.d/limits.conf > /dev/null <<EOF
[Service]
MemoryAccounting=yes
MemoryMax=1G
OOMScoreAdjust=0
EOF
    sudo systemctl daemon-reload
    sudo systemctl enable --now silo

    for _ in $(seq 30); do
        curl -fs "$ENDPOINT/minio/health/live" > /dev/null && break
        sleep 1
    done
    curl -fs "$ENDPOINT/minio/health/live" > /dev/null || { echo "Silo did not start: journalctl -u silo" >&2; exit 1; }

    env_set AWS_ENDPOINT "$ENDPOINT"
    env_set AWS_ACCESS_KEY_ID "$key"
    env_set AWS_SECRET_ACCESS_KEY "$secret"
    env_set AWS_DEFAULT_REGION us-east-1
    env_set AWS_BUCKET capstrack-private
    env_set AWS_PUBLIC_BUCKET capstrack-public
    env_set AWS_USE_PATH_STYLE_ENDPOINT true
    env_set FILES_ON_S3 false
    echo "Silo is running. The site still uses the disk."
    ;;
copy)
    on_silo
    on_silo --check
    ;;
switch)
    on_silo
    env_set FILES_ON_S3 true
    reload_site
    # Whatever was uploaded in the moment before the switch.
    sudo -u www-data php artisan files:copy-to-s3

    url=$(env_get APP_URL)/storage/silo-check.txt
    php artisan tinker --execute="Storage::disk('public')->put('silo-check.txt', 'ok');"
    got=$(curl -fsS "$url" || true)
    php artisan tinker --execute="Storage::disk('public')->delete('silo-check.txt');"
    [ "$got" = ok ] || { echo "FAILED: $url did not come back. Run: bash deploy/silo.sh back" >&2; exit 1; }
    echo "The site now uses Silo."
    ;;
back)
    sudo -u www-data php artisan files:copy-to-s3 --back \
        || echo "WARNING: could not bring files back from Silo. Uploads since the switch are still on it." >&2
    env_set FILES_ON_S3 false
    reload_site
    echo "The site uses the disk again."
    ;;
*)
    sed -n 2,10p "$0"
    exit 1
    ;;
esac
