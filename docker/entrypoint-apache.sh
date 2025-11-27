#!/bin/sh
# entrypoint-apache.sh
# Runs database migrations (if available) and starts Apache in foreground.

set -e

# Default port if not provided by environment
PORT=${PORT:-80}
PHINX_ENV=${PHINX_ENV:-development}

echo "[entrypoint] Starting entrypoint script"
echo "[entrypoint] Using PORT=${PORT}, PHINX_ENV=${PHINX_ENV}"

# Update Apache listen port (ports.conf)
if [ -f /etc/apache2/ports.conf ]; then
  echo "[entrypoint] Updating /etc/apache2/ports.conf to listen on ${PORT}"
  sed -i "s/Listen [0-9]\+/Listen ${PORT}/g" /etc/apache2/ports.conf || true
fi

# Update default site VirtualHost port
if [ -f /etc/apache2/sites-available/000-default.conf ]; then
  echo "[entrypoint] Updating VirtualHost to use port ${PORT}"
  sed -i "s/<VirtualHost \*:[0-9]\+>/<VirtualHost *:${PORT}>/g" /etc/apache2/sites-available/000-default.conf || true
fi

# Run migrations if phinx is available
if [ -f ./vendor/bin/phinx ]; then
  echo "[entrypoint] Found phinx, running migrations (env=${PHINX_ENV})"
  # Try running migrations but don't fail the container if DB is temporarily unavailable
  php ./vendor/bin/phinx migrate -e "${PHINX_ENV}" || echo "[entrypoint] phinx migrate failed or database not ready. Continuing to start webserver."
else
  echo "[entrypoint] phinx not found at ./vendor/bin/phinx — skipping migrations"
fi

# Ensure www-data owns storage and cache (helpful when mounted volumes are used)
if [ -d /var/www/html/storage ]; then
  chown -R www-data:www-data /var/www/html/storage || true
fi
if [ -d /var/www/html/bootstrap/cache ]; then
  chown -R www-data:www-data /var/www/html/bootstrap/cache || true
fi

echo "[entrypoint] Executing: $@"
exec "$@"
