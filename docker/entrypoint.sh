#!/bin/sh
# Aggressively clear bootstrap cache files
echo "=== Cleaning bootstrap/cache ==="
rm -f /var/www/html/bootstrap/cache/config.php
rm -f /var/www/html/bootstrap/cache/routes.php
rm -f /var/www/html/bootstrap/cache/services.php
rm -f /var/www/html/bootstrap/cache/packages.php

# Diagnostics: print resolved hosts for debugging
echo "=== Environment Diagnostics ==="
echo "DB_HOST=$DB_HOST | DB_PORT=$DB_PORT | DB_DATABASE=$DB_DATABASE"
echo "REDIS_HOST=$REDIS_HOST | REDIS_PORT=$REDIS_PORT"
echo "SESSION_DRIVER=$SESSION_DRIVER | CACHE_DRIVER=$CACHE_DRIVER"
echo "APP_URL=$APP_URL | FORCE_HTTPS=$FORCE_HTTPS"
echo "REDIS_CLIENT=${REDIS_CLIENT:-predis}"


# Replace ${PORT} in nginx config with the actual environment variable
if [ -z "$PORT" ]; then
  export PORT=80
fi

# Apply the port to the nginx configuration
sed -i "s/\${PORT}/${PORT}/g" /etc/nginx/sites-available/default

# Ensure storage directories exist and are writable
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/framework/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 777 /var/www/html/storage/logs

# Clear any stale caches
echo "=== Aggressively cleaning bootstrap/cache ==="
rm -f /var/www/html/bootstrap/cache/*.php

if [ "$CLEAR_CACHES_ON_BOOT" = "false" ]; then
    echo "=== Skipping artisan cache commands ==="
else
    echo "=== Running artisan cache clear commands ==="
    php artisan config:clear || echo "Config clear failed"
    php artisan route:clear || echo "Route clear failed"
    php artisan view:clear || echo "View clear failed"
    php artisan cache:clear || echo "Cache clear failed"
fi

# Ensure session table exists if not already there
echo "=== Ensuring session table ==="
# First check if the table exists in the DB
table_exists=$(php artisan tinker --execute="echo Schema::hasTable('sessions') ? '1' : '0';" | tail -n 1 | tr -d '\r')
if [ "$table_exists" = "0" ]; then
    # Only if table doesn't exist, check migrations folder
    if ! ls database/migrations/*_create_sessions_table.php 1> /dev/null 2>&1; then
        php artisan session:table || echo "Session table generation failed"
    fi
else
    echo "Sessions table already exists in database."
fi

# Run migrations with visible output
echo "=== Running migrations ==="
php artisan migrate --force 2>&1 || echo "WARNING: Migration failed, check logs above"

# Provision system records and users
if [ "$DB_SEED_ON_BOOT" = "false" ]; then
    echo "=== Skipping database seeder ==="
else
    echo "=== Running database seeder ==="
    php artisan db:seed --force 2>&1 || echo "WARNING: Seeding failed, check logs above"
fi

# Setup Passport OAuth keys and clients
echo "=== Setting up Passport ==="
php artisan passport:keys --force 2>&1 || echo "WARNING: Passport keys failed"

# Create OAuth clients directly via SQL (passport:client uses UUIDs which don't match the integer ID column)
php artisan tinker --execute="
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Personal Access Client
if (DB::table('oauth_clients')->where('personal_access_client', 1)->count() === 0) {
    DB::table('oauth_clients')->insert([
        'name' => 'PmApi',
        'secret' => Hash::make('secret'),
        'provider' => 'users',
        'redirect' => 'http://localhost',
        'personal_access_client' => 1,
        'password_client' => 0,
        'revoked' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    \$clientId = DB::table('oauth_clients')->where('personal_access_client', 1)->value('id');
    DB::table('oauth_personal_access_clients')->insertOrIgnore([
        'client_id' => \$clientId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo 'Personal access client created (ID: ' . \$clientId . ')' . PHP_EOL;
} else {
    echo 'Personal access client already exists' . PHP_EOL;
}

// Password Grant Client
if (DB::table('oauth_clients')->where('password_client', 1)->count() === 0) {
    DB::table('oauth_clients')->insert([
        'name' => 'Password Grant',
        'secret' => Hash::make('secret'),
        'provider' => 'users',
        'redirect' => 'http://localhost',
        'personal_access_client' => 0,
        'password_client' => 1,
        'revoked' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo 'Password grant client created' . PHP_EOL;
} else {
    echo 'Password grant client already exists' . PHP_EOL;
}
" 2>&1 || echo "WARNING: OAuth client creation failed"

# Fix permissions AGAIN after migrate/seed created files as root
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage

# Start Supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
