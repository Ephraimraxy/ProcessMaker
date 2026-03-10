#!/bin/sh

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
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/bootstrap/cache

# Clear any stale caches
echo "=== Clearing caches ==="
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Run migrations with visible output
echo "=== Running migrations ==="
php artisan migrate --force 2>&1 || echo "WARNING: Migration failed, check logs above"

# Provision system records and users
echo "=== Running database seeder ==="
php artisan db:seed --force 2>&1 || echo "WARNING: Seeding failed, check logs above"

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
