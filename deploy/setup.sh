#!/usr/bin/env bash
set -euo pipefail

#===============================================================================
# Kheedma Academy — First-time server setup
#
# Creates the atomic-deploy directory skeleton and a shared .env (with a freshly
# generated APP_KEY). Idempotent: safe to re-run; never overwrites an existing
# shared/.env. Run ONCE before the first deploy.sh.
#
#   bash setup.sh
#===============================================================================

BASE_DIR="/srv/www/kheedma-academy"
SHARED_DIR="$BASE_DIR/shared"
APP_URL="https://kheedma.hyperscore.cloud"

echo "[SETUP] Creating directory skeleton under $BASE_DIR ..."
mkdir -p "$BASE_DIR"/{releases,deploy,logs,database}
mkdir -p "$SHARED_DIR"/storage/{app/public,framework/{cache/data,sessions,views},logs}

if [ ! -f "$SHARED_DIR/.env" ]; then
    echo "[SETUP] Generating shared/.env ..."
    APP_KEY="base64:$(openssl rand -base64 32)"
    cat > "$SHARED_DIR/.env" <<ENV
APP_NAME="Kheedma Academy"
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_TIMEZONE=Asia/Jakarta
APP_URL=${APP_URL}

APP_LOCALE=en
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=stack
LOG_LEVEL=error

# No database is required for the static UI preview. File-based drivers keep the
# app fully functional without a DB. Switch DB_* + drivers when the form lands.
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kheedma_academy
DB_USERNAME=
DB_PASSWORD=

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true

CACHE_STORE=file
QUEUE_CONNECTION=sync
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local

VITE_APP_NAME="\${APP_NAME}"
ENV
    echo "[SETUP] shared/.env created (APP_KEY generated)."
else
    echo "[SETUP] shared/.env already exists — left untouched."
fi

# storage/ must be writable by the web user (php-fpm runs as www-data)
sudo chown -R www-data:www-data "$SHARED_DIR/storage" 2>/dev/null || echo "[WARN] chown storage failed"
sudo chmod -R 2775 "$SHARED_DIR/storage" 2>/dev/null || echo "[WARN] chmod storage failed"

echo "[SETUP] Done. Directory tree:"
ls -la "$BASE_DIR"
echo ""
echo "Next: bash $BASE_DIR/deploy/deploy.sh main"
