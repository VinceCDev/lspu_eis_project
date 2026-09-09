#!/bin/sh
set -e

cd /var/www/html

# --- Ensure the storage skeleton exists (the named volume starts empty) --
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# --- Resolve APP_KEY ----------------------------------------------------
# If none was supplied via the environment, generate one once and persist
# it in the storage volume so every container (and every redeploy) reuses
# the same key — otherwise sessions / encrypted data break on restart.
if [ -z "${APP_KEY}" ]; then
    if [ ! -s storage/app/.appkey ]; then
        php artisan key:generate --show --no-ansi > storage/app/.appkey
    fi
    APP_KEY="$(cat storage/app/.appkey)"
    export APP_KEY
    echo "[entrypoint] Using persisted APP_KEY from storage/app/.appkey"
fi

# --- One-time bootstrap: primary "app" container only ------------------
if [ "${APP_BOOTSTRAP:-run}" = "run" ]; then
    echo "[entrypoint] Waiting for database / running migrations..."
    tries=0
    until php artisan migrate --force 2>/dev/null; do
        tries=$((tries + 1))
        if [ "$tries" -ge 40 ]; then
            echo "[entrypoint] DB not ready after 40 tries — continuing anyway."
            break
        fi
        sleep 3
    done

    echo "[entrypoint] Caching config / routes / views..."
    php artisan package:discover --ansi 2>/dev/null || true
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan storage:link 2>/dev/null || true
fi

exec "$@"
