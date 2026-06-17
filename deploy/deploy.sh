#!/usr/bin/env bash
set -euo pipefail

#===============================================================================
# Kheedma Academy — Deployment Script
#
# Atomic, zero-downtime deploy: clones the repo fresh into a timestamped release
# directory, links shared resources, builds, then flips the `current` symlink.
# Adapted from the hyperscore hst-client deploy pattern.
#
# Usage: bash deploy.sh [branch] [--migrate]
#   branch    — Git branch to deploy (default: main)
#   --migrate — Back up the database and run `php artisan migrate` after deploy
#               (only needed once the app uses a database; the UI preview does not)
#
# Examples:
#   bash deploy.sh                 # deploy main
#   bash deploy.sh main --migrate  # deploy + backup + migrate
#===============================================================================

# Configuration
BASE_DIR="/srv/www/kheedma-academy"
# Public repo → clone over HTTPS (no key needed). When the repo goes private,
# add a read-only deploy key and switch this to:
#   git@github-kheedma-academy:ak-rocksdev/kheedma-academy.git
GIT_REPO="https://github.com/ak-rocksdev/kheedma-academy.git"
KEEP_RELEASES=5
PHP_FPM_SERVICE="php8.4-fpm"      # Laravel 13 requires PHP >= 8.3
WEB_USER="www-data"
WEB_GROUP="www-data"
DB_BACKUP_DIR="$BASE_DIR/database"
KEEP_BACKUPS=10
LOG_DIR="$BASE_DIR/logs"
KEEP_LOGS=30

#===============================================================================

# Logging — re-exec self with all output teed to a dated log file.
# A foreground pipeline (cmd | tee) survives non-interactive SSH; `exec > >(tee)`
# does not — its backgrounded tee gets killed, the next write hits SIGPIPE, and
# `set -e` aborts the deploy. So we re-run ourselves once, piped into tee.
if [ -z "${DEPLOY_LOG_ACTIVE:-}" ]; then
    mkdir -p "$LOG_DIR"
    LOG_FILE="$LOG_DIR/deploy-$(date +%Y%m%d_%H%M%S).log"
    export DEPLOY_LOG_ACTIVE=1 LOG_FILE
    ls -1t "$LOG_DIR"/deploy-*.log 2>/dev/null | tail -n +$((KEEP_LOGS + 1)) | xargs -r rm -f || true
    set +e
    bash "$0" "$@" 2>&1 | tee -a "$LOG_FILE"
    rc=${PIPESTATUS[0]}
    set -e
    exit "$rc"
fi

# Parse arguments: branch and --migrate flag
BRANCH="main"
RUN_MIGRATE=false
for arg in "$@"; do
    if [ "$arg" = "--migrate" ]; then
        RUN_MIGRATE=true
    elif [[ "$arg" != --* ]]; then
        BRANCH="$arg"
    fi
done

RELEASES_DIR="$BASE_DIR/releases"
SHARED_DIR="$BASE_DIR/shared"
CURRENT_LINK="$BASE_DIR/current"

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DEPLOY_AT=$(date '+%Y-%m-%d %H:%M:%S')
NEW_RELEASE="$RELEASES_DIR/$TIMESTAMP"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()  { echo -e "${GREEN}[DEPLOY]${NC} $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; }
step()  { echo -e "${CYAN}[STEP]${NC} $1"; }

PREVIOUS_RELEASE=""
SYMLINK_SWITCHED=false
BACKUP_FILE=""

if [ "$RUN_MIGRATE" = true ]; then TOTAL_STEPS=10; else TOTAL_STEPS=9; fi

#---------------------------------------
# Rollback on failure
#---------------------------------------
cleanup_on_failure() {
    local exit_code=$?
    if [ $exit_code -ne 0 ]; then
        error "Deployment failed! (exit code: $exit_code)"
        if [ "$SYMLINK_SWITCHED" = true ] && [ -n "$PREVIOUS_RELEASE" ] && [ -d "$PREVIOUS_RELEASE" ]; then
            error "Rolling back symlink to previous release ..."
            ln -sfn "$PREVIOUS_RELEASE" "$CURRENT_LINK"
            sudo systemctl reload "$PHP_FPM_SERVICE" 2>/dev/null || true
            info "Rolled back to $(basename "$PREVIOUS_RELEASE")"
        fi
        if [ -d "$NEW_RELEASE" ]; then
            error "Removing failed release $TIMESTAMP ..."
            rm -rf "$NEW_RELEASE"
        fi
        if [ -n "$BACKUP_FILE" ] && [ -s "$BACKUP_FILE" ]; then
            warn "A database backup exists at: $BACKUP_FILE"
        fi
        exit $exit_code
    fi
}
trap cleanup_on_failure EXIT

#---------------------------------------
# Pre-flight checks
#---------------------------------------
step "Running pre-flight checks ..."
[ -d "$RELEASES_DIR" ]    || { error "Releases dir missing: $RELEASES_DIR (run setup.sh first)"; exit 1; }
[ -d "$SHARED_DIR" ]      || { error "Shared dir missing: $SHARED_DIR (run setup.sh first)"; exit 1; }
[ -f "$SHARED_DIR/.env" ] || { error ".env missing: $SHARED_DIR/.env (run setup.sh first)"; exit 1; }
command -v php      >/dev/null 2>&1 || { error "php not found"; exit 1; }
command -v composer >/dev/null 2>&1 || { error "composer not found"; exit 1; }
command -v npm      >/dev/null 2>&1 || { error "npm not found"; exit 1; }
command -v git      >/dev/null 2>&1 || { error "git not found"; exit 1; }

if [ -L "$CURRENT_LINK" ]; then PREVIOUS_RELEASE=$(readlink -f "$CURRENT_LINK"); fi

if ! git ls-remote --heads "$GIT_REPO" "$BRANCH" | grep -q "refs/heads/${BRANCH}\$"; then
    error "Branch '$BRANCH' not found on remote $GIT_REPO"; exit 1
fi
info "Branch '$BRANCH' verified on remote"
info "Deploying branch '$BRANCH' as release $TIMESTAMP"
echo ""
CURRENT_STEP=0

#---------------------------------------
# 1. Clone into new release directory
#---------------------------------------
CURRENT_STEP=$((CURRENT_STEP + 1)); step "$CURRENT_STEP/$TOTAL_STEPS Cloning repository ..."
mkdir -p "$NEW_RELEASE"; cd "$NEW_RELEASE"
git clone -b "$BRANCH" "$GIT_REPO" . 2>&1
GIT_SHA=$(git rev-parse --short HEAD); GIT_SHA_FULL=$(git rev-parse HEAD)
GIT_MSG=$(git log -1 --pretty=format:'%s'); GIT_AUTHOR=$(git log -1 --pretty=format:'%an')
GIT_CDATE=$(git log -1 --pretty=format:'%ci')
info "SHA: $GIT_SHA - $GIT_MSG"
cat > "$NEW_RELEASE/DEPLOY_INFO.txt" <<INFO
release   : $TIMESTAMP
deployed  : $DEPLOY_AT
branch    : $BRANCH
sha       : $GIT_SHA_FULL
sha_short : $GIT_SHA
commit    : $GIT_MSG
author    : $GIT_AUTHOR
committed : $GIT_CDATE
INFO
rm -rf "$NEW_RELEASE/.git"

#---------------------------------------
# 2. Link shared resources
#---------------------------------------
CURRENT_STEP=$((CURRENT_STEP + 1)); step "$CURRENT_STEP/$TOTAL_STEPS Linking shared resources ..."
rm -rf "$NEW_RELEASE/storage"
ln -s "$SHARED_DIR/storage" "$NEW_RELEASE/storage"
ln -s "$SHARED_DIR/.env" "$NEW_RELEASE/.env"
ln -s "$NEW_RELEASE/storage/app/public" "$NEW_RELEASE/public/storage"
mkdir -p "$NEW_RELEASE/bootstrap/cache"; chmod 775 "$NEW_RELEASE/bootstrap/cache"
info ".env, storage, public/storage linked"

#---------------------------------------
# 3. Composer install
#---------------------------------------
CURRENT_STEP=$((CURRENT_STEP + 1)); step "$CURRENT_STEP/$TOTAL_STEPS Installing PHP dependencies ..."
cd "$NEW_RELEASE"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist 2>&1

#---------------------------------------
# 4. NPM build (Vite)
#---------------------------------------
CURRENT_STEP=$((CURRENT_STEP + 1)); step "$CURRENT_STEP/$TOTAL_STEPS Building frontend assets (Vite) ..."
cd "$NEW_RELEASE"
set -a; source "$SHARED_DIR/.env"; set +a
npm ci --no-audit --no-fund 2>&1 | tail -3
npm run build 2>&1
rm -rf "$NEW_RELEASE/node_modules"
info "Frontend build complete. node_modules removed."

#---------------------------------------
# 5. Laravel cache
#---------------------------------------
CURRENT_STEP=$((CURRENT_STEP + 1)); step "$CURRENT_STEP/$TOTAL_STEPS Caching Laravel config ..."
cd "$NEW_RELEASE"
php artisan config:cache 2>&1 || { warn "config:cache failed, clearing instead"; php artisan config:clear 2>&1; }
php artisan route:cache 2>&1 || warn "route:cache failed (non-critical)"
php artisan view:cache 2>&1

#---------------------------------------
# 6. Permissions
#---------------------------------------
CURRENT_STEP=$((CURRENT_STEP + 1)); step "$CURRENT_STEP/$TOTAL_STEPS Fixing permissions ..."
sudo chown -R "$WEB_USER:$WEB_GROUP" "$SHARED_DIR/storage" 2>/dev/null || warn "Could not chown storage"
sudo chmod -R 2775 "$SHARED_DIR/storage" 2>/dev/null || warn "Could not chmod storage"

#---------------------------------------
# 7. Activate release (atomic symlink switch)
#---------------------------------------
CURRENT_STEP=$((CURRENT_STEP + 1)); step "$CURRENT_STEP/$TOTAL_STEPS Switching symlink ..."
ln -sfn "$NEW_RELEASE" "$CURRENT_LINK"; SYMLINK_SWITCHED=true
sudo chown -h "$WEB_USER:$WEB_GROUP" "$CURRENT_LINK" 2>/dev/null || true
info "current -> releases/$TIMESTAMP"
sudo systemctl reload "$PHP_FPM_SERVICE" 2>/dev/null || warn "Could not reload PHP-FPM"
sleep 2
sudo systemctl reload "$PHP_FPM_SERVICE" 2>/dev/null || true
info "PHP-FPM reloaded (twice, OPcache race fix)"

#---------------------------------------
# 8. Database backup + migrate (only with --migrate)
#---------------------------------------
if [ "$RUN_MIGRATE" = true ]; then
    CURRENT_STEP=$((CURRENT_STEP + 1)); step "$CURRENT_STEP/$TOTAL_STEPS Backing up database and migrating ..."
    DB_NAME=$(grep '^DB_DATABASE=' "$SHARED_DIR/.env" | cut -d'=' -f2 | tr -d '"' | tr -d "'")
    DB_USER=$(grep '^DB_USERNAME=' "$SHARED_DIR/.env" | cut -d'=' -f2 | tr -d '"' | tr -d "'")
    DB_PASS=$(grep '^DB_PASSWORD=' "$SHARED_DIR/.env" | cut -d'=' -f2 | tr -d '"' | tr -d "'")
    if [ -n "$DB_NAME" ] && [ -n "$DB_USER" ]; then
        mkdir -p "$DB_BACKUP_DIR"
        BACKUP_FILE="$DB_BACKUP_DIR/${DB_NAME}_${TIMESTAMP}.sql.gz"
        info "Creating database backup: $(basename "$BACKUP_FILE")"
        mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
            --single-transaction --routines --triggers --no-tablespaces 2>/dev/null | gzip > "$BACKUP_FILE"
        if [ -s "$BACKUP_FILE" ]; then
            info "Backup complete -> $BACKUP_FILE"
            ls -1t "$DB_BACKUP_DIR"/*.sql.gz 2>/dev/null | tail -n +$((KEEP_BACKUPS + 1)) | xargs -r rm -f
            cd "$NEW_RELEASE"; php artisan migrate --force --no-interaction 2>&1; info "Migrations complete"
        else
            error "Backup empty! Skipping migrations."; rm -f "$BACKUP_FILE"; BACKUP_FILE=""
        fi
    else
        warn "No DB credentials in .env — skipping backup and migrations."
    fi
fi

#---------------------------------------
# 9. Cleanup old releases
#---------------------------------------
CURRENT_STEP=$((CURRENT_STEP + 1)); step "$CURRENT_STEP/$TOTAL_STEPS Cleaning up old releases ..."
cd "$RELEASES_DIR"
ls -1dt */ 2>/dev/null | tail -n +$((KEEP_RELEASES + 1)) | xargs -r rm -rf
info "Keeping last $KEEP_RELEASES releases."

# Self-update deploy script from the freshly deployed release
if [ -f "$NEW_RELEASE/deploy/deploy.sh" ]; then
    cp "$NEW_RELEASE/deploy/deploy.sh" "$BASE_DIR/deploy/deploy.sh"
    chmod +x "$BASE_DIR/deploy/deploy.sh"
fi

echo ""
echo "========================================="
info "Deployment successful!"
echo "========================================="
echo "  Release:  $TIMESTAMP"
echo "  Git SHA:  $GIT_SHA"
echo "  Commit:   $GIT_MSG"
echo "  Symlink:  current -> releases/$TIMESTAMP"
echo "  Log:      $LOG_FILE"
echo "$DEPLOY_AT | release=$TIMESTAMP | branch=$BRANCH | sha=$GIT_SHA | $GIT_MSG" >> "$LOG_DIR/deployments.log"
echo ""
