#!/bin/bash
# Runs the REAL bootstrap section of docker/entrypoint.sh against a brand-new empty database on the scratch MySQL, to prove the
# fresh-install path (schema.sql import -> baseline migrations recorded -> remaining migrations executed -> verify) end to end,
# and (with --break) that a failing migration STOPS the deployment instead of being marked as applied.
#   tests/Performance/entrypoint_dryrun.sh [--break]
set -u
cd "$(dirname "$0")/../.."
ROOT=$(pwd)
MYSQL_BIN="C:/Program Files/MySQL/MySQL Server 9.6/bin/mysql.exe"
DB=lspu_eis_fresh
"$MYSQL_BIN" -h 127.0.0.1 -P 3391 -u root -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;" || exit 1

# the mysql client with the scratch server's coordinates (the entrypoint word-splits $MYSQL: a wrapper avoids the path with spaces)
printf '#!/bin/sh\nexec "%s" -h 127.0.0.1 -P 3391 -u root %s "$@"\n' "$MYSQL_BIN" "$DB" > /tmp/mysqlw.sh

# a throw-away copy of the entrypoint pointed at the scratch server (only the path / the mysql client line differ)
sed -e "s#cd /var/www/html#cd '$ROOT'#" \
    -e 's#^    MYSQL="mysql .*#    MYSQL="sh /tmp/mysqlw.sh"#' \
    -e '/^chown /d;/^chmod /d;/^exec /d' \
    -e '/artisan config:cache/d;/artisan route:cache/d;/artisan view:cache/d' \
    docker/entrypoint.sh > /tmp/entrypoint_dryrun.sh

BREAK=""
if [ "${1:-}" = "--break" ]; then
  BREAK="$ROOT/database/migrations/2026_09_23_000000_zz_deliberately_failing.php"
  cat > "$BREAK" <<'EOF'
<?php
use Illuminate\Database\Migrations\Migration;
return new class extends Migration { public function up(): void { throw new \RuntimeException('deliberate failure'); } };
EOF
fi

export MYSQL_BIN DB_HOST=127.0.0.1 DB_PORT=3391 DB_DATABASE=$DB DB_USERNAME=root DB_PASSWORD= APP_BOOTSTRAP=run APP_KEY=base64:$(php -r 'echo base64_encode(random_bytes(32));')
export SESSION_DRIVER=array CACHE_STORE=array QUEUE_CONNECTION=sync MAIL_MAILER=log
sh /tmp/entrypoint_dryrun.sh
RC=$?
echo "=== entrypoint exit code: $RC"
"$MYSQL_BIN" -h 127.0.0.1 -P 3391 -u root -N -e "SELECT CONCAT('recorded migration: ', migration) FROM $DB.migrations ORDER BY id" 2>/dev/null
[ -n "$BREAK" ] && rm -f "$BREAK"
"$MYSQL_BIN" -h 127.0.0.1 -P 3391 -u root -e "SHOW INDEX FROM $DB.alumni" 2>/dev/null | awk 'NR==1 || !seen[\$3]++ {print \$3}' | sort -u | tr '\n' ' '; echo
exit $RC
