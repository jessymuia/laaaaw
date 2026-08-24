#!/usr/bin/env bash
set -euo pipefail

# ENG-6: called by .github/workflows/deploy.yml for both the staging and
# production jobs (only the target environment differs). Matches the
# deployment model docs/ops/DEPLOYMENT.md already describes — a plain PHP-FPM
# server with a Supervisor-managed queue worker, not a containerized
# platform — rather than inventing a different, undocumented deploy shape.
#
# Required environment variables (set as GitHub Actions secrets/vars per
# environment, never hardcoded here — see deploy.yml):
#   DEPLOY_SSH_KEY  - private key with access to the target server
#   DEPLOY_HOST     - user@host for the target server
#   DEPLOY_PATH     - absolute path to the app's deployed directory on that server
#
# This script assumes the target directory is already an initialized
# git checkout of this repo on a release branch/tag, with its own .env
# already configured for that environment (this script never touches
# .env — secrets never pass through CI for the destination server, only
# the SSH key to reach it).

TARGET_ENV="${1:?Usage: deploy.sh <staging|production>}"

if [[ "$TARGET_ENV" != "staging" && "$TARGET_ENV" != "production" ]]; then
    echo "Unknown target environment: $TARGET_ENV (expected 'staging' or 'production')" >&2
    exit 1
fi

: "${DEPLOY_SSH_KEY:?DEPLOY_SSH_KEY is required}"
: "${DEPLOY_HOST:?DEPLOY_HOST is required}"
: "${DEPLOY_PATH:?DEPLOY_PATH is required}"

echo "==> Deploying to $TARGET_ENV ($DEPLOY_HOST:$DEPLOY_PATH)"

SSH_KEY_FILE="$(mktemp)"
trap 'rm -f "$SSH_KEY_FILE"' EXIT
echo "$DEPLOY_SSH_KEY" > "$SSH_KEY_FILE"
chmod 600 "$SSH_KEY_FILE"

ssh_exec() {
    ssh -i "$SSH_KEY_FILE" -o StrictHostKeyChecking=accept-new "$DEPLOY_HOST" "$@"
}

echo "==> Building frontend assets locally (uploaded, not built on the server)"
npm ci
npm run build

echo "==> Pulling latest release on $DEPLOY_HOST"
ssh_exec "cd '$DEPLOY_PATH' && git fetch --depth=1 origin main && git reset --hard origin/main"

echo "==> Uploading built frontend assets (public/js, public/css, mix-manifest.json)"
rsync -az -e "ssh -i $SSH_KEY_FILE -o StrictHostKeyChecking=accept-new" \
    public/js public/css public/mix-manifest.json \
    "$DEPLOY_HOST:$DEPLOY_PATH/public/"

echo "==> Installing PHP dependencies (production, no dev packages)"
ssh_exec "cd '$DEPLOY_PATH' && composer install --no-dev --optimize-autoloader --no-interaction"

echo "==> Running database migrations"
ssh_exec "cd '$DEPLOY_PATH' && php artisan migrate --force"

echo "==> Refreshing cached config/routes/views"
ssh_exec "cd '$DEPLOY_PATH' && php artisan config:cache && php artisan route:cache && php artisan view:cache"

echo "==> Restarting the queue worker (see docs/ops/DEPLOYMENT.md — Supervisor-managed)"
# graceful restart: queue:restart signals workers to finish their current
# job then exit; Supervisor's autorestart brings a fresh worker straight
# back up with the newly deployed code, per Laravel's own recommended
# zero-downtime deploy pattern (rather than a hard supervisorctl restart,
# which would kill an in-flight job — e.g. a half-sent SMS — mid-way).
ssh_exec "cd '$DEPLOY_PATH' && php artisan queue:restart"

echo "==> Deploy to $TARGET_ENV complete"
