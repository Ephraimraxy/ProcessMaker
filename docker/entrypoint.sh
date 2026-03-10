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

# Run migrations with visible output
echo "=== Running migrations ==="
php artisan migrate --force 2>&1 || echo "WARNING: Migration failed, check logs above"

# Provision system records and users
echo "=== Running database seeder ==="
php artisan db:seed --force 2>&1 || echo "WARNING: Seeding failed, check logs above"

# Setup Passport OAuth keys and clients
echo "=== Setting up Passport ==="
php artisan passport:keys --force 2>&1 || echo "WARNING: Passport keys failed"
php artisan passport:client --personal --name="PmApi" --no-interaction 2>&1 || echo "WARNING: Passport personal client failed"
php artisan passport:client --password --name="Password Grant" --provider=users --no-interaction 2>&1 || echo "WARNING: Passport password client failed"

# Fix permissions AGAIN after migrate/seed created files as root
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage

# Start Supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
