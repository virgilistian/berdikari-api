#!/bin/sh
set -e

cd /var/www/html

echo "==> Installing PHP dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader

echo "==> Setting up environment..."
if [ ! -f .env ]; then
    cat > .env << 'EOF'
APP_KEY=
EOF
fi

# Generate app key if not set in environment or .env
if [ -z "$(grep -v '^#' .env | grep '^APP_KEY=' | cut -d= -f2)" ]; then
    echo "==> Generating application key..."
    php artisan key:generate --force
fi

echo "==> Clearing configuration cache..."
php artisan config:clear

echo "==> Running database migrations..."
php artisan migrate --force

echo "==> Seeding database..."
php artisan db:seed --class="Modules\\IAM\\Database\\Seeders\\IAMDatabaseSeeder" --force 2>/dev/null || true

echo "==> Creating storage symlink..."
php artisan storage:link --force 2>/dev/null || true

echo "==> Berdikari API is ready at http://0.0.0.0:8000"
# --no-reload makes `artisan serve` pass the full container environment to its
# worker process. Without it, serve strips every non-allowlisted env var (incl.
# REDIS_HOST/DB_HOST) from the worker, so HTTP requests fall back to the mounted
# host .env (127.0.0.1:63790) and fail with "Connection refused".
exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
