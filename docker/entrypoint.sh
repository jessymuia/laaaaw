#!/bin/sh
# First-run setup for the dockerised local-testing stack. The source tree
# is bind-mounted, so vendor/, .env, and storage writes land in the host
# checkout (containers run as the host UID to keep ownership sane).
set -e

cd /var/www/html

if [ "$APP_SETUP" = "1" ]; then
    [ -f .env ] || cp .env.example .env

    if [ ! -f vendor/autoload.php ]; then
        composer install --prefer-dist --no-progress --no-interaction
    fi

    if ! grep -q '^APP_KEY=.\{1,\}' .env; then
        php artisan key:generate --force
    fi

    php artisan migrate --force

    # Seed demo data (roles, permissions, deterministic test users) only
    # on an empty database, so repeated `docker compose up`s don't pile
    # duplicate rows on top of each other.
    USERS=$(php -r '
        $pdo = new PDO(
            sprintf("mysql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT") ?: "3306", getenv("DB_DATABASE")),
            getenv("DB_USERNAME"),
            getenv("DB_PASSWORD")
        );
        echo $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    ')
    if [ "$USERS" = "0" ]; then
        php artisan db:seed --force
        echo ""
        echo "Seeded demo data. Log in at ${APP_URL:-http://localhost:8000} with:"
        echo "  e2e-admin@lawfirm.test    / E2ePassword!123  (admin)"
        echo "  e2e-advocate@lawfirm.test / E2ePassword!123  (advocate)"
        echo "  e2e-clerk@lawfirm.test    / E2ePassword!123  (read-only clerk)"
        echo ""
    fi
else
    # Sidecars (queue worker, scheduler) wait for the app container to
    # finish first-run setup instead of racing it through composer
    # install and migrations.
    until [ -f vendor/autoload.php ] && [ -f .env ]; do
        echo "waiting for the app container to finish setup..."
        sleep 3
    done
fi

exec "$@"
