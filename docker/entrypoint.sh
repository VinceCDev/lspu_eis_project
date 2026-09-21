#!/bin/sh
# Container entrypoint. `set -e`: any failing command below aborts the container (docker then restarts it and the
# deployment is visibly unhealthy) - nothing that fails is ever reported as done.
set -e

cd /var/www/html

# --- Ensure the storage skeleton exists (the named volume starts empty) --
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    storage/app/private/imports
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
            echo "[entrypoint] FATAL: database not reachable after 60 tries - refusing to start." >&2
            exit 1
        fi
        sleep 2
    done

    # This app's real schema starts from an unversioned dump (database/schema.sql). On a fresh database import it, and record as
    # "applied" ONLY the migrations that dump already contains (database/schema.baseline.txt). Every other migration is then
    # executed for real by `artisan migrate` below.
    TABLE_COUNT=$($MYSQL -N -B -e \
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${DB_DATABASE:-lspu_eis}'")

    if [ "${TABLE_COUNT:-0}" -lt 5 ]; then
        echo "[entrypoint] Fresh database (${TABLE_COUNT} tables) — importing database/schema.sql"
        $MYSQL < database/schema.sql
        $MYSQL -e "CREATE TABLE IF NOT EXISTS migrations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(255) NOT NULL, batch INT NOT NULL)"
        grep -v '^[[:space:]]*#' database/schema.baseline.txt | grep -v '^[[:space:]]*$' | while read -r name; do
            $MYSQL -e "INSERT INTO migrations (migration, batch) SELECT '$name', 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '$name')"
        done
        echo "[entrypoint] Schema imported; baseline migrations recorded (the rest run below)."
    else
        echo "[entrypoint] Database already has ${TABLE_COUNT} tables — skipping schema import."
    fi

    # A failing migration stops the deployment: it is never recorded as done and the app never starts on a half-migrated schema.
    echo "[entrypoint] Running migrations..."
    if ! php artisan migrate --force; then
        echo "[entrypoint] FATAL: php artisan migrate failed - refusing to start (the failed migration was NOT marked as applied)." >&2
        exit 1
    fi

    # The indexes / tables the reporting + import paths depend on must really exist (SHOW INDEX FROM alumni, in effect).
    echo "[entrypoint] Verifying required tables and indexes..."
    php artisan reporting:verify || { echo "[entrypoint] FATAL: schema verification failed." >&2; exit 1; }

    echo "[entrypoint] Seeding reference data (campuses) + default superadmin..."
    php artisan db:seed --force || echo "[entrypoint] WARNING: seeding skipped - continuing."

    # First deploy of the summary tables: queue the initial build (a queue-summaries worker runs it; until a campus's summary is
    # built the pages simply use their original live queries). No-op once every partition has been built.
    if [ "$(php artisan tinker --execute='echo DB::table("rpt_state")->whereNotNull("built_at")->count();' 2>/dev/null | tail -n 1)" = "0" ]; then
        echo "[entrypoint] Queueing the initial dashboard/report summary build..."
        php artisan reporting:rebuild || echo "[entrypoint] WARNING: could not queue the summary build (run: php artisan reporting:rebuild)"
    fi

    echo "[entrypoint] Caching config / routes / views..."
    php artisan package:discover --ansi 2>/dev/null || true
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan storage:link 2>/dev/null || true
fi

exec "$@"
