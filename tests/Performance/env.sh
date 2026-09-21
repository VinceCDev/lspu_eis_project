# Source this file:  source tests/Performance/env.sh
# Points the app at the isolated scratch DB + storage. Process env vars beat .env (Laravel's dotenv is immutable), so the
# dev database on :3307 is never touched. Nothing secret is stored here: APP_KEY and the other .env keys are read at runtime.
export DB_HOST=127.0.0.1 DB_PORT=3391 DB_DATABASE=lspu_eis_perf DB_USERNAME=root DB_PASSWORD=
export SESSION_DRIVER=database CACHE_STORE=database QUEUE_CONNECTION=${QUEUE_CONNECTION:-database}   # queue architecture: start workers with tests/Performance/start_workers.sh
export APP_ENV=local APP_DEBUG=false LOG_LEVEL=warning MAIL_MAILER=log
# (no LARAVEL_STORAGE_PATH override: under variables_order=GPCS the web and CLI workers resolved storage_path() differently, so uploaded workbooks were not found by the worker)
export PERF_DEBUG=0
export APP_URL=http://127.0.0.1:8091
export GEMINI_API_KEY=          # never call the real Gemini API from a load test
export MAIL_HOST=127.0.0.1 MAIL_PORT=1 MAIL_USERNAME= MAIL_PASSWORD=

# Windows mod_php only: dotenv's putenv() is not thread-safe (intermittent "No application encryption key" / sqlite fallback),
# so export every remaining non-secret .env key into the process environment. Not needed on Linux php-fpm.
_perf_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
if [ -f "$_perf_root/.env" ]; then
  while IFS='=' read -r _k _v; do
    case "$_k" in ''|\#*|SESSION_PATH|*PASSWORD*|*SECRET*|*TOKEN*|GEMINI*|SUPERADMIN*|MAIL_*) continue ;; esac
    [ -n "${!_k+x}" ] && continue                      # keep the overrides above
    _v="${_v%\"}"; _v="${_v#\"}"
    export "$_k=$_v"
  done < <(grep -E '^[A-Z_][A-Z0-9_]*=' "$_perf_root/.env" | tr -d '\r')
fi
unset _perf_root _k _v
