#!/usr/bin/env bash
set -e

# Simple entrypoint for Render: wait DB, run migrations if available, then start Apache

DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"
RETRIES=12
SLEEP_SECONDS=3

echo "[entrypoint] Waiting for database ${DB_HOST}:${DB_PORT} (up to $((RETRIES*SLEEP_SECONDS))s) ..."
i=0
until (echo > /dev/tcp/${DB_HOST}/${DB_PORT}) >/dev/null 2>&1; do
  i=$((i+1))
  if [ "$i" -ge "$RETRIES" ]; then
    echo "[entrypoint] Timeout waiting for ${DB_HOST}:${DB_PORT}, continuing anyway"
    break
  fi
  sleep ${SLEEP_SECONDS}
done

# Ensure logs directory exists and is writable by www-data
mkdir -p /var/www/html/logs
chown -R www-data:www-data /var/www/html/logs || true

# If DB_SSL_CA is provided directly as an env var (PEM text or file path), write it to a file.
# This avoids committing the CA into the repo while allowing the container to use it.
if [ -n "${DB_SSL_CA:-}" ]; then
  # If DB_SSL_CA points to an existing file path inside the container, keep it.
  if [ -f "${DB_SSL_CA}" ]; then
    echo "[entrypoint] DB_SSL_CA is a file path and exists: ${DB_SSL_CA}"
    export DB_SSL_CA_PATH="${DB_SSL_CA_PATH:-${DB_SSL_CA}}"
  else
    # Otherwise assume DB_SSL_CA is the PEM contents; write to standard path
    CA_PATH="/etc/ssl/certs/tidb_ca.pem"
    echo "[entrypoint] Writing DB SSL CA contents from env to ${CA_PATH}"
    mkdir -p "$(dirname "$CA_PATH")"
    # Preserve newlines when writing the env var to file
    printf '%s' "$DB_SSL_CA" > "$CA_PATH"
    chmod 644 "$CA_PATH" || true
    export DB_SSL_CA="$CA_PATH"
    export DB_SSL_CA_PATH="$CA_PATH"
  fi
fi

# If DB_SSL_CA_B64 is provided, decode to file (unless a path is already set)
if [ -z "${DB_SSL_CA_PATH:-}" ] && [ -n "${DB_SSL_CA_B64:-}" ]; then
  CA_PATH="/etc/ssl/certs/tidb_ca_from_b64.pem"
  echo "[entrypoint] Writing DB SSL CA from DB_SSL_CA_B64 to ${CA_PATH}"
  printf '%s' "${DB_SSL_CA_B64}" | base64 -d > "$CA_PATH"
  chmod 644 "$CA_PATH" || true
  export DB_SSL_CA="$CA_PATH"
  export DB_SSL_CA_PATH="$CA_PATH"
fi

# If no DB_SSL_CA env var was set but the platform mounted a secret file in a common
# Docker secret path (e.g. /run/secrets/...), prefer that file. This lets platforms
# that provide secret-files work without extra env var manipulation.
if [ -z "${DB_SSL_CA:-}" ]; then
  COMMON_SECRET_PATHS=(/run/secrets/tidb_ca.pem /run/secrets/DB_SSL_CA /run/secrets/db_ssl_ca /etc/secrets/tidb_ca.pem /etc/ssl/secrets/tidb_ca.pem /secrets/tidb_ca.pem)
  for p in "${COMMON_SECRET_PATHS[@]}"; do
    if [ -f "$p" ]; then
      echo "[entrypoint] Found CA secret file at $p, setting DB_SSL_CA to that path"
      export DB_SSL_CA="$p"
      export DB_SSL_CA_PATH="$p"
      break
    fi
  done
fi

# If still not found, look for any .pem files in common secret directories (handles names with spaces)
if [ -z "${DB_SSL_CA:-}" ]; then
  for dir in /run/secrets /etc/secrets /etc/ssl/secrets /secrets; do
    if [ -d "$dir" ]; then
      # shellcheck disable=SC2086
      for f in "$dir"/*.pem; do
        if [ -f "$f" ]; then
          echo "[entrypoint] Found CA .pem file at $f, setting DB_SSL_CA to that path"
          export DB_SSL_CA="$f"
          export DB_SSL_CA_PATH="$f"
          break 2
        fi
      done
    fi
  done
fi

# Diagnostic logging: show what CA path will be used by Phinx/PDO and basic file info.
if [ -n "${DB_SSL_CA:-}" ]; then
  echo "[entrypoint] DEBUG: DB_SSL_CA=${DB_SSL_CA}"
  if [ -f "${DB_SSL_CA}" ]; then
    echo "[entrypoint] DEBUG: CA file exists at ${DB_SSL_CA} - listing details:";
    ls -l "${DB_SSL_CA}" || true
    echo "[entrypoint] DEBUG: Showing first 10 lines of CA (BEGIN/--- header expected):";
    # show first 10 lines so we can verify BEGIN/END without dumping whole cert
    head -n 10 "${DB_SSL_CA}" || true
    echo "[entrypoint] DEBUG: Showing last 2 lines of CA (END line expected):";
    tail -n 2 "${DB_SSL_CA}" || true
  else
    echo "[entrypoint] DEBUG: CA file does not exist at ${DB_SSL_CA}"
  fi
else
  echo "[entrypoint] DEBUG: DB_SSL_CA not set"
fi

# If a PDO SSL diagnostic script exists, run it so logs show PHP/PDO/openssl status and connection attempts.
if [ -f /var/www/html/docker/pdo_ssl_test.php ]; then
  echo "[entrypoint] Running docker/pdo_ssl_test.php to collect PDO/SSL diagnostics..."
  php /var/www/html/docker/pdo_ssl_test.php || true
  echo "[entrypoint] Finished PDO/SSL diagnostic script"
fi

# Run migrations if phinx is available. Try a few times (DB may still be warming up).
if [ -x "vendor/bin/phinx" ]; then
  echo "[entrypoint] phinx found, attempting migrations (env: ${PHINX_ENV:-production})"
  MAX_ATTEMPTS=5
  attempt=1
  while [ $attempt -le $MAX_ATTEMPTS ]; do
    echo "[entrypoint] phinx attempt #${attempt}..."
    if vendor/bin/phinx migrate -e ${PHINX_ENV:-production}; then
      echo "[entrypoint] phinx migrate succeeded"
      break
    else
      echo "[entrypoint] phinx migrate failed on attempt ${attempt}"
      attempt=$((attempt+1))
      sleep 3
    fi
  done
  if [ $attempt -gt $MAX_ATTEMPTS ]; then
    echo "[entrypoint] phinx migrate failed after ${MAX_ATTEMPTS} attempts, aborting start"
    exit 1
  fi
else
  echo "[entrypoint] phinx not found, skipping migrations"
fi

echo "[entrypoint] Starting Apache..."
exec "$@"
