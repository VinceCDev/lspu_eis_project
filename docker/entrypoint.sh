#!/bin/sh
set -e

cd /var/www/html

# Only the primary "app" container runs the one-time bootstrap (install
# deps, key, migrate, cache). The queue / scheduler containers set
# APP_BOOTSTRAP=skip so they don't race each other.
if [ "${APP_BOOTSTRAP:-run}" = "run" ]; then

    if [ ! -f vendor/autoload.php ]; then
        echo "[entrypoint] Installing composer dependencies..."
        composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
    fi

    if [ ! -f .env ]; then
        echo "[entrypoint] No .env found — copying .env.example (EDIT IT AND REDEPLOY)"
        cp .env.example .env
    fi

    if ! grep -q "^APP_KEY=base64:" .env; then
        echo "[entrypoint] Generating APP_KEY..."
        php artisan key:generate --force
    fi

    echo "[entrypoint] Waiting for database..."
    tries=0
    until php artisan migrate --force 2>/dev/null; do
        tries=$((tries + 1))
        if [ "$tries" -ge 30 ]; then
            echo "[entrypoint] DB still not ready after 30 tries — continuing anyway."
            break
        fi
        sleep 2
    done

    echo "[entrypoint] Caching config / routes / views..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan storage:link 2>/dev/null || true

    chown -R www-data:www-data storage bootstrap/cache public/uploads 2>/dev/null || true
    chmod -R 775 storage bootstrap/cache 2>/dev/null || true
fi

exec "$@"
