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

    MYSQL="mysql -h ${DB_HOST:-db} -P ${DB_PORT:-3306} -u ${DB_USERNAME:-lspu_eis} -p${DB_PASSWORD:-lspu_eis_db_pass} ${DB_DATABASE:-lspu_eis}"

    echo "[entrypoint] Waiting for database to accept connections..."
    tries=0
    until $MYSQL -e "SELECT 1" >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [ "$tries" -ge 60 ]; then
            echo "[entrypoint] Database not reachable after 60 tries — continuing."
            break
        fi
        sleep 2
    done

    # This app's real schema is an unversioned dump (database/schema.sql),
    # not `php artisan migrate`. On a fresh database, import it, then record
    # the incremental migration files as already applied (schema.sql is a
    # current dump that already contains their changes).
    TABLE_COUNT=$($MYSQL -N -B -e \
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${DB_DATABASE:-lspu_eis}'" \
        2>/dev/null || echo 0)

    if [ "${TABLE_COUNT:-0}" -lt 5 ]; then
        echo "[entrypoint] Fresh database (${TABLE_COUNT} tables) — importing database/schema.sql"
        $MYSQL < database/schema.sql
        $MYSQL -e "CREATE TABLE IF NOT EXISTS migrations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(255) NOT NULL, batch INT NOT NULL)"
        for f in database/migrations/*.php; do
            name=$(basename "$f" .php)
            $MYSQL -e "INSERT INTO migrations (migration, batch) SELECT '$name', 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '$name')"
        done
        echo "[entrypoint] Schema imported; migration files marked as applied."
    else
        echo "[entrypoint] Database already has ${TABLE_COUNT} tables — skipping schema import."
    fi

    echo "[entrypoint] Running migrations (any new ones only)..."
    php artisan migrate --force || echo "[entrypoint] migrate reported an issue — continuing."

    echo "[entrypoint] Ensuring default superadmin account..."
    php artisan db:seed --class=SuperadminSeeder --force || echo "[entrypoint] superadmin seed skipped — continuing."

    echo "[entrypoint] Caching config / routes / views..."
    php artisan package:discover --ansi 2>/dev/null || true
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan storage:link 2>/dev/null || true
fi

exec "$@"
