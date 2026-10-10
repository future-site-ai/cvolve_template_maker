#!/bin/sh
# Waits for PostgreSQL, runs migrations and creates the first designer account if configured.
set -e
cd /srv/cvolve_template_maker
export OPCACHE_VALIDATE_TIMESTAMPS="${OPCACHE_VALIDATE_TIMESTAMPS:-0}"

until php bin/console dbal:run-sql "SELECT 1" >/dev/null 2>&1; do
    echo "Waiting for the database..."
    sleep 2
done
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
if [ -n "$MAKER_ADMIN_EMAIL" ] && [ -n "$MAKER_ADMIN_PASSWORD" ]; then
    php bin/console app:create-user "$MAKER_ADMIN_EMAIL" --password="$MAKER_ADMIN_PASSWORD" --name="Designer" --if-missing
fi
chown -R www-data:www-data var
exec "$@"
